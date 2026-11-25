<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

/**
 * Immutable snapshot of all CMS content, loaded in one go. Resolves translations with a fallback to the main language.
 *
 * @phpstan-type Language array{iso2: string, iso3: string, name: string, flag_media_id: int|null, is_main: bool}
 * @phpstan-type Item array{id: int, section: string, group: string}
 * @phpstan-type Media array{id: int, kind: string, path: string, variants: array<string, mixed>, width: int, height: int}
 */
final readonly class Content
{
    /**
     * @param list<Language> $languages in display order
     * @param array<string, bool> $nav section slug => shown in the main menu
     * @param list<Item> $items in display order
     * @param array<string, array<string, array<string, string>>> $values scope => field => lang ('' = untranslated) => value
     * @param array<int, Media> $media id => media
     */
    public function __construct(
        public array $languages,
        public array $nav,
        public array $items,
        public array $values,
        public array $media,
    ) {
        assert(count($languages) <= Definitions::MAX_LANGUAGES, 'too many languages');
    }

    public function mainLanguage(): string
    {
        foreach ($this->languages as $language) {
            if ($language['is_main']) {
                return $language['iso2'];
            }
        }

        return $this->languages[0]['iso2'] ?? 'en';
    }

    /** @return Language|null */
    public function language(string $iso2): ?array
    {
        foreach ($this->languages as $language) {
            if ($language['iso2'] === $iso2) {
                return $language;
            }
        }

        return null;
    }

    /** Translated value in $lang; falls back to the main language, then to ''. */
    public function text(string $scope, string $field, string $lang): string
    {
        $translations = $this->values[$scope][$field] ?? [];
        $value = $translations[$lang] ?? '';

        return $value !== '' ? $value : ($translations[$this->mainLanguage()] ?? '');
    }

    /** Value that is the same in every language (URL, number, image id). */
    public function value(string $scope, string $field): string
    {
        return $this->values[$scope][$field][''] ?? '';
    }

    /** True when $lang has its own value (not the fallback). */
    public function isTranslated(string $scope, string $field, string $lang): bool
    {
        return ($this->values[$scope][$field][$lang] ?? '') !== '';
    }

    /** @return list<int> item ids of one section group, in display order */
    public function itemIds(string $section, string $group): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            if ($item['section'] === $section && $item['group'] === $group) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }

    /** @return Media|null */
    public function mediaById(int $id): ?array
    {
        return $this->media[$id] ?? null;
    }

    /**
     * The media an image field points at.
     *
     * @return Media|null
     */
    public function image(string $scope, string $field): ?array
    {
        $id = $this->value($scope, $field);

        return ctype_digit($id) ? $this->mediaById((int) $id) : null;
    }

    public static function itemScope(int $id): string
    {
        return 'item:' . $id;
    }

    public static function sectionScope(string $slug): string
    {
        return 'section:' . $slug;
    }
}
