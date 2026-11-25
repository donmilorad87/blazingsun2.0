<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

use BlazingSun\Api\Models\ContentRepository;
use RuntimeException;

/**
 * Fills an empty CMS with the original page content (content/seed.json) exactly once.
 * Migrations run on every deploy; the site_meta flag keeps admin edits and deletions from being overwritten.
 * Image values written as "@key" point at the seed's own media entries.
 */
final readonly class ContentSeeder
{
    public const string FLAG = 'content_seeded';

    public function __construct(
        private ContentRepository $repository,
        private string $seedFile,
    ) {
    }

    /** @return bool true when the seed was applied now, false when it already had been */
    public function run(): bool
    {
        if ($this->repository->meta(self::FLAG) !== null) {
            return false;
        }
        $seed = json_decode((string) file_get_contents($this->seedFile), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($seed)) {
            throw new RuntimeException("Invalid seed file {$this->seedFile}");
        }

        $this->repository->transaction(function () use ($seed): void {
            $media = [];
            foreach ($seed['media'] as $entry) {
                $media['@' . $entry['key']] = (string) $this->repository->createMedia($entry['kind'], $entry['path'], $entry['variants'], $entry['width'], $entry['height']);
            }
            foreach ($seed['languages'] as $language) {
                $this->repository->saveLanguage($language['iso2'], $language['iso3'], $language['name'], (int) $media['@' . $language['flag']]);
            }
            $this->repository->setMainLanguage($seed['main']);
            foreach ($seed['values'] as $scope => $fields) {
                $this->writeFields((string) $scope, $fields, $media);
            }
            $order = [];
            foreach ($seed['items'] as $item) {
                $key = $item['section'] . '/' . $item['group'];
                $order[$key] = ($order[$key] ?? -1) + 1;
                $id = $this->repository->createItem($item['section'], $item['group'], $order[$key]);
                $this->writeFields(Content::itemScope($id), $item['values'], $media);
            }
            $this->repository->setMeta(self::FLAG, gmdate('c'));
        });

        return true;
    }

    /**
     * @param array<string, string|array<string, string>> $fields field => value, or field => {iso2: value}
     * @param array<string, string> $media "@key" => media id
     */
    private function writeFields(string $scope, array $fields, array $media): void
    {
        foreach ($fields as $field => $value) {
            $translations = is_array($value) ? $value : ['' => $media[$value] ?? $value];
            foreach ($translations as $lang => $text) {
                $this->repository->setValue($scope, $field, (string) $lang, $text);
            }
        }
    }
}
