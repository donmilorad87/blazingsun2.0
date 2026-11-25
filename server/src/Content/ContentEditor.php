<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

use BlazingSun\Api\Models\ContentRepository;
use Closure;
use PDOException;

/**
 * Every change made in the admin portal: validated against Definitions, written in one transaction,
 * then $onChange runs (it clears the rendered-page cache).
 *
 * @phpstan-import-type Write from Validator
 */
final readonly class ContentEditor
{
    /** @param Closure(): void $onChange */
    public function __construct(
        private ContentRepository $repository,
        private Closure $onChange,
    ) {
    }

    /**
     * Saves a section's own fields and all its item groups. Items missing from the payload are deleted,
     * items without an id are created, and the payload order becomes the display order.
     *
     * @param array<mixed> $payload {values: {...}, groups: {group: [{id?: int, values: {...}}]}}
     */
    public function saveSection(string $slug, array $payload): void
    {
        $definition = Definitions::sections()[$slug] ?? throw ValidationException::single('section', 'Unknown section');
        $content = $this->repository->snapshot();
        $validator = new Validator($content);

        $head = $validator->fields($definition['fields'], $payload['values'] ?? [], 'values');
        $groupsInput = $payload['groups'] ?? [];
        if (!is_array($groupsInput)) {
            throw ValidationException::single('groups', 'Expected an object of item lists');
        }
        foreach (array_keys($groupsInput) as $name) {
            if (!isset($definition['groups'][$name])) {
                throw ValidationException::single("groups.$name", 'Unknown item group');
            }
        }

        $groups = [];
        foreach ($definition['groups'] as $name => $group) {
            $groups[$name] = $this->items($validator, $content, $slug, $name, $group, $groupsInput[$name] ?? []);
        }
        $validator->assertValid();

        $this->repository->transaction(function () use ($slug, $head, $groups, $content): void {
            $this->write(Content::sectionScope($slug), $head);
            foreach ($groups as $name => $entries) {
                $this->writeGroup($content, $slug, $name, $entries);
            }
        });
        ($this->onChange)();
    }

    /** @param array<mixed> $payload {values: {...}} */
    public function saveSettings(string $scope, array $payload): void
    {
        $fields = Definitions::settings()[$scope] ?? throw ValidationException::single('scope', 'Unknown settings');
        $validator = new Validator($this->repository->snapshot());
        $writes = $validator->fields($fields, $payload['values'] ?? [], 'values');
        $validator->assertValid();

        $this->repository->transaction(fn () => $this->write($scope, $writes));
        ($this->onChange)();
    }

    /**
     * Main menu: per section, whether it is shown and its label in every language.
     *
     * @param array<mixed> $payload {items: {slug: {in_nav: bool, label: {iso2: text}}}}
     */
    public function saveMenu(array $payload): void
    {
        $items = $payload['items'] ?? null;
        if (!is_array($items)) {
            throw ValidationException::single('items', 'Expected an object of menu entries');
        }
        $validator = new Validator($this->repository->snapshot());
        $labelField = [['name' => 'nav_label', 'label' => 'Main menu label', 'type' => 'text', 'required' => true]];
        $changes = [];
        foreach ($items as $slug => $entry) {
            $slug = (string) $slug;
            if (!(Definitions::sections()[$slug]['nav'] ?? false) || !is_array($entry) || !is_bool($entry['in_nav'] ?? null)) {
                throw ValidationException::single("items.$slug", 'Unknown menu entry or missing in_nav');
            }
            $changes[$slug] = [$entry['in_nav'], $validator->fields($labelField, ['nav_label' => $entry['label'] ?? []], "items.$slug")];
        }
        $validator->assertValid();

        $this->repository->transaction(function () use ($changes): void {
            foreach ($changes as $slug => [$inNav, $writes]) {
                $this->repository->setNav($slug, $inNav);
                $this->write(Content::sectionScope($slug), $writes);
            }
        });
        ($this->onChange)();
    }

    /**
     * Adds a language or updates its iso3, name and flag.
     *
     * @param array<mixed> $payload {iso2, iso3, name, flag}
     */
    public function saveLanguage(array $payload): void
    {
        $iso2 = strtolower(trim(self::string($payload['iso2'] ?? null)));
        $iso3 = strtolower(trim(self::string($payload['iso3'] ?? null)));
        $name = trim(self::string($payload['name'] ?? null));
        $flag = $payload['flag'] ?? null;
        $content = $this->repository->snapshot();

        $errors = array_filter([
            'iso2' => preg_match('/^[a-z]{2}$/', $iso2) === 1 ? null : 'Two letters (ISO 639-1), e.g. de',
            'iso3' => preg_match('/^[a-z]{3}$/', $iso3) === 1 ? null : 'Three letters (ISO 639-2), e.g. deu',
            'name' => $name !== '' && mb_strlen($name) <= 64 ? null : 'Name, 1–64 characters (e.g. Deutsch)',
            'flag' => is_int($flag) && $content->mediaById($flag) !== null ? null : 'Upload an SVG flag',
        ]);
        if ($content->language($iso2) === null && count($content->languages) >= Definitions::MAX_LANGUAGES) {
            $errors['iso2'] = 'At most ' . Definitions::MAX_LANGUAGES . ' languages';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            $this->repository->saveLanguage($iso2, $iso3, $name, (int) $flag);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                throw ValidationException::single('iso3', 'Already used by another language');
            }
            throw $error;
        }
        ($this->onChange)();
    }

    public function deleteLanguage(string $iso2): void
    {
        $language = $this->repository->snapshot()->language($iso2) ?? throw ValidationException::single('iso2', 'Unknown language');
        if ($language['is_main']) {
            throw ValidationException::single('iso2', 'Choose another main language first');
        }
        $this->repository->deleteLanguage($iso2);
        ($this->onChange)();
    }

    /** Makes $iso2 the main language, but only once every required text has its own value in it. */
    public function setMainLanguage(string $iso2): void
    {
        $content = $this->repository->snapshot();
        if ($content->language($iso2) === null) {
            throw ValidationException::single('iso2', 'Unknown language');
        }
        $missing = self::missingRequired($content, $iso2);
        if ($missing !== []) {
            throw ValidationException::single('iso2', sprintf('Translate the %d required texts first (e.g. %s)', count($missing), implode(', ', array_slice($missing, 0, 3))));
        }
        $this->repository->setMainLanguage($iso2);
        ($this->onChange)();
    }

    /** @return list<string> "scope.field" of required translated fields with no own value in $lang */
    public static function missingRequired(Content $content, string $lang): array
    {
        $missing = [];
        $check = static function (string $scope, array $fields) use ($content, $lang, &$missing): void {
            foreach ($fields as $field) {
                if ($field['required'] && Definitions::isTranslated($field['type']) && !$content->isTranslated($scope, $field['name'], $lang)) {
                    $missing[] = "$scope.{$field['name']}";
                }
            }
        };
        foreach (Definitions::sections() as $slug => $section) {
            $check(Content::sectionScope($slug), $section['fields']);
            foreach ($section['groups'] as $name => $group) {
                foreach ($content->itemIds($slug, $name) as $id) {
                    $check(Content::itemScope($id), $group['fields']);
                }
            }
        }
        foreach (Definitions::settings() as $scope => $fields) {
            $check($scope, $fields);
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $group
     * @return list<array{id: int|null, writes: list<Write>}>
     */
    private function items(Validator $validator, Content $content, string $slug, string $name, array $group, mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input)) {
            throw ValidationException::single("groups.$name", 'Expected a list of items');
        }
        if (count($input) > $group['max']) {
            throw ValidationException::single("groups.$name", "At most {$group['max']} items");
        }
        $existing = $content->itemIds($slug, $name);
        $entries = [];
        foreach ($input as $index => $entry) {
            $id = is_array($entry) ? ($entry['id'] ?? null) : null;
            if ($id !== null && !in_array($id, $existing, true)) {
                throw ValidationException::single("groups.$name.$index.id", 'Unknown item');
            }
            $entries[] = ['id' => $id, 'writes' => $validator->fields($group['fields'], is_array($entry) ? ($entry['values'] ?? []) : null, "groups.$name.$index")];
        }

        return $entries;
    }

    /** @param list<array{id: int|null, writes: list<Write>}> $entries */
    private function writeGroup(Content $content, string $slug, string $name, array $entries): void
    {
        $kept = array_filter(array_column($entries, 'id'), is_int(...));
        foreach (array_diff($content->itemIds($slug, $name), $kept) as $removed) {
            $this->repository->deleteItem($removed);
        }
        foreach ($entries as $order => $entry) {
            $id = $entry['id'] ?? $this->repository->createItem($slug, $name, $order);
            $this->repository->setItemOrder($id, $order);
            $this->write(Content::itemScope($id), $entry['writes']);
        }
    }

    /** @param list<Write> $writes */
    private function write(string $scope, array $writes): void
    {
        foreach ($writes as $write) {
            $this->repository->setValue($scope, $write['field'], $write['lang'], $write['value']);
        }
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
