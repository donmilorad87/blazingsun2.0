<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

/**
 * Turns submitted form values into value writes, checked against the field definitions.
 * Translated fields are submitted as {iso2: text}; the rest as a string. A missing or empty value means "delete it",
 * which for translations means "use the main language". Collects every error instead of stopping at the first.
 *
 * @phpstan-import-type Field from Definitions
 * @phpstan-type Write array{field: string, lang: string, value: string}
 */
final class Validator
{
    public const int MAX_TEXT_LENGTH = 5000;

    public const int MAX_PLAIN_LENGTH = 500;

    /** @var array<string, string> path => message */
    private array $errors = [];

    public function __construct(private readonly Content $content)
    {
    }

    /**
     * @param list<Field> $fields
     * @return list<Write>
     */
    public function fields(array $fields, mixed $input, string $path): array
    {
        assert($path !== '', 'error paths need a prefix');
        if ($input === null) {
            $input = [];
        }
        if (!is_array($input)) {
            $this->errors[$path] = 'Expected an object of field values';
            return [];
        }

        $known = array_column($fields, 'name');
        foreach (array_keys($input) as $name) {
            if (!in_array($name, $known, true)) {
                $this->errors["$path.$name"] = 'Unknown field';
            }
        }

        $writes = [];
        foreach ($fields as $field) {
            $value = $input[$field['name']] ?? null;
            $fieldPath = "$path.{$field['name']}";
            $writes = [...$writes, ...(Definitions::isTranslated($field['type'])
                ? $this->translated($field, $value, $fieldPath)
                : $this->single($field, $value, $fieldPath))];
        }

        return $writes;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Throws when anything failed validation. */
    public function assertValid(): void
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }

    /**
     * @param Field $field
     * @return list<Write>
     */
    private function translated(array $field, mixed $value, string $path): array
    {
        if ($value !== null && !is_array($value)) {
            $this->errors[$path] = 'Expected translations as {language: text}';
            return [];
        }
        $value ??= [];
        foreach (array_keys($value) as $lang) {
            if ($this->content->language((string) $lang) === null) {
                $this->errors["$path.$lang"] = 'Unknown language';
            }
        }

        $writes = [];
        foreach ($this->content->languages as $language) {
            $iso2 = $language['iso2'];
            $text = $this->text($value[$iso2] ?? '', self::MAX_TEXT_LENGTH, "$path.$iso2");
            if ($text === null) {
                continue;
            }
            if ($field['type'] === 'rich' && ($error = RichText::nestingError($text)) !== null) {
                $this->errors["$path.$iso2"] = $error;
            }
            if ($field['required'] && $language['is_main'] && $text === '') {
                $this->errors["$path.$iso2"] = 'Required in the main language';
            }
            $writes[] = ['field' => $field['name'], 'lang' => $iso2, 'value' => $text];
        }

        return $writes;
    }

    /**
     * @param Field $field
     * @return list<Write>
     */
    private function single(array $field, mixed $value, string $path): array
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        $text = $this->text($value ?? '', self::MAX_PLAIN_LENGTH, $path);
        if ($text === null) {
            return [];
        }
        if ($text === '') {
            if ($field['required']) {
                $this->errors[$path] = 'Required';
            }
            return [['field' => $field['name'], 'lang' => '', 'value' => '']];
        }

        $error = match ($field['type']) {
            'url' => self::isUrl($text) ? null : 'Use https://…, http://…, mailto:…, tel:… or #section',
            'select' => in_array($text, $field['options'] ?? [], true) ? null : 'Choose one of: ' . implode(', ', $field['options'] ?? []),
            'image' => ctype_digit($text) && $this->content->mediaById((int) $text) !== null ? null : 'Unknown image',
            default => null,
        };
        if ($error !== null) {
            $this->errors[$path] = $error;
        }

        return [['field' => $field['name'], 'lang' => '', 'value' => $text]];
    }

    /** Trimmed string, or null (with an error) when it isn't a string or is too long. */
    private function text(mixed $value, int $maxLength, string $path): ?string
    {
        if (!is_string($value)) {
            $this->errors[$path] = 'Expected text';
            return null;
        }
        // Control characters (except newlines and tabs) never belong in page text.
        $text = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
        if (mb_strlen($text) > $maxLength) {
            $this->errors[$path] = "At most $maxLength characters";
            return null;
        }

        return $text;
    }

    public static function isUrl(string $url): bool
    {
        return preg_match('~^(https?://[^\s<>"]+|mailto:[^\s<>"@]+@[^\s<>"]+|tel:\+?[0-9 ()-]{3,}|#[a-z][a-z0-9-]*)$~i', $url) === 1;
    }
}
