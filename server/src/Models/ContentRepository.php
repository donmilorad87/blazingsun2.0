<?php

declare(strict_types=1);

namespace BlazingSun\Api\Models;

use BlazingSun\Api\Content\Content;
use BlazingSun\Api\Database\Connection;

/** CMS content tables, through stored procedures only. */
final readonly class ContentRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** Loads everything (five procedure calls; the whole site is a few hundred rows). */
    public function snapshot(): Content
    {
        $languages = array_map(static fn (array $row): array => [
            'iso2' => (string) $row['iso2'],
            'iso3' => (string) $row['iso3'],
            'name' => (string) $row['name'],
            'flag_media_id' => $row['flag_media_id'] === null ? null : (int) $row['flag_media_id'],
            'is_main' => (bool) $row['is_main'],
        ], $this->connection->call('sp_language_list'));

        $nav = [];
        foreach ($this->connection->call('sp_section_list') as $row) {
            $nav[(string) $row['slug']] = (bool) $row['in_nav'];
        }

        $items = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'section' => (string) $row['section_slug'],
            'group' => (string) $row['item_group'],
        ], $this->connection->call('sp_item_list'));

        $values = [];
        foreach ($this->connection->call('sp_value_list') as $row) {
            $values[(string) $row['scope']][(string) $row['field']][(string) $row['lang']] = (string) $row['value'];
        }

        $media = [];
        foreach ($this->connection->call('sp_media_list') as $row) {
            $variants = json_decode((string) $row['variants'], true, 8, JSON_THROW_ON_ERROR);
            $media[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'path' => (string) $row['path'],
                'variants' => is_array($variants) ? $variants : [],
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
            ];
        }

        return new Content($languages, $nav, $items, $values, $media);
    }

    public function setValue(string $scope, string $field, string $lang, string $value): void
    {
        $this->connection->call('sp_value_set', [$scope, $field, $lang, $value]);
    }

    public function createItem(string $section, string $group, int $sortOrder): int
    {
        return (int) $this->connection->callScalar('sp_item_create', [$section, $group, $sortOrder]);
    }

    public function setItemOrder(int $id, int $sortOrder): void
    {
        $this->connection->call('sp_item_set_order', [$id, $sortOrder]);
    }

    public function deleteItem(int $id): void
    {
        $this->connection->call('sp_item_delete', [$id]);
    }

    public function setNav(string $section, bool $inNav): void
    {
        $this->connection->call('sp_section_set_nav', [$section, $inNav ? 1 : 0]);
    }

    public function saveLanguage(string $iso2, string $iso3, string $name, ?int $flagMediaId): void
    {
        $this->connection->call('sp_language_save', [$iso2, $iso3, $name, $flagMediaId]);
    }

    public function deleteLanguage(string $iso2): void
    {
        $this->connection->call('sp_language_delete', [$iso2]);
    }

    public function setMainLanguage(string $iso2): void
    {
        $this->connection->call('sp_language_set_main', [$iso2]);
    }

    /** @param array<string, mixed> $variants */
    public function createMedia(string $kind, string $path, array $variants, int $width, int $height): int
    {
        return (int) $this->connection->callScalar('sp_media_create', [$kind, $path, json_encode($variants, JSON_THROW_ON_ERROR), $width, $height]);
    }

    public function meta(string $key): ?string
    {
        $value = $this->connection->callScalar('sp_meta_get', [$key]);

        return $value === false || $value === null ? null : (string) $value;
    }

    public function setMeta(string $key, string $value): void
    {
        $this->connection->call('sp_meta_set', [$key, $value]);
    }

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        return $this->connection->transaction($work);
    }
}
