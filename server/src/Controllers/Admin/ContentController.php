<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers\Admin;

use BlazingSun\Api\Content\Content;
use BlazingSun\Api\Content\ContentEditor;
use BlazingSun\Api\Content\Definitions;
use BlazingSun\Api\Content\RichText;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Models\ContentRepository;
use BlazingSun\Api\Page\Picture;
use BlazingSun\Api\Page\SchemaGraph;

/**
 * The admin portal's data: GET /api/admin/content returns everything (definitions + current values);
 * the POST endpoints save one form each. Saves are validated by ContentEditor (422 with field errors).
 */
final readonly class ContentController
{
    public function __construct(
        private ContentRepository $repository,
        private ContentEditor $editor,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return new JsonResponse(200, self::dump($this->repository->snapshot()));
    }

    /** POST {section, values, groups} */
    public function saveSection(Request $request): JsonResponse
    {
        $body = $request->json();
        $this->editor->saveSection(self::string($body['section'] ?? null), $body);

        return $this->show($request);
    }

    /** POST {scope, values} — scope: header | footer | labels */
    public function saveSettings(Request $request): JsonResponse
    {
        $body = $request->json();
        $this->editor->saveSettings(self::string($body['scope'] ?? null), $body);

        return $this->show($request);
    }

    /** POST {items: {slug: {in_nav, label}}} */
    public function saveMenu(Request $request): JsonResponse
    {
        $this->editor->saveMenu($request->json());

        return $this->show($request);
    }

    /** POST {iso2, iso3, name, flag} */
    public function saveLanguage(Request $request): JsonResponse
    {
        $this->editor->saveLanguage($request->json());

        return $this->show($request);
    }

    /** POST {iso2} */
    public function deleteLanguage(Request $request): JsonResponse
    {
        $this->editor->deleteLanguage(self::string($request->json()['iso2'] ?? null));

        return $this->show($request);
    }

    /** POST {iso2} */
    public function setMainLanguage(Request $request): JsonResponse
    {
        $this->editor->setMainLanguage(self::string($request->json()['iso2'] ?? null));

        return $this->show($request);
    }

    /**
     * Everything the portal shows. Translated values are {iso2: text} (only languages that have their own value),
     * the others a plain string.
     *
     * @return array<string, mixed>
     */
    public static function dump(Content $content): array
    {
        $values = static function (string $scope, array $fields) use ($content): array {
            $out = [];
            foreach ($fields as $field) {
                $stored = $content->values[$scope][$field['name']] ?? [];
                $out[$field['name']] = Definitions::isTranslated($field['type']) ? (object) $stored : ($stored[''] ?? '');
            }

            return $out;
        };

        $sections = [];
        foreach (Definitions::sections() as $slug => $section) {
            $groups = [];
            foreach ($section['groups'] as $name => $group) {
                $groups[$name] = array_map(
                    static fn (int $id): array => ['id' => $id, 'values' => $values(Content::itemScope($id), $group['fields'])],
                    $content->itemIds($slug, $name),
                );
            }
            $sections[$slug] = ['in_nav' => $content->nav[$slug] ?? false, 'values' => $values(Content::sectionScope($slug), $section['fields']), 'groups' => $groups];
        }

        $settings = [];
        foreach (Definitions::settings() as $scope => $fields) {
            $settings[$scope] = $values($scope, $fields);
        }

        $main = $content->mainLanguage();

        return [
            'definitions' => Definitions::toArray(),
            'languages' => array_map(static fn (array $language): array => [
                'iso2' => $language['iso2'],
                'iso3' => $language['iso3'],
                'name' => $language['name'],
                'flag' => $language['flag_media_id'],
                'is_main' => $language['is_main'],
                'missing' => count(ContentEditor::missingRequired($content, $language['iso2'])),
            ], $content->languages),
            'media' => (object) array_map(static fn (array $media): array => [
                'id' => $media['id'],
                'kind' => $media['kind'],
                'url' => Picture::previewUrl($media),
                'width' => $media['width'],
                'height' => $media['height'],
            ], $content->media),
            'sections' => $sections,
            'settings' => $settings,
            'schema' => SchemaGraph::build(
                '/',
                $main,
                array_column($content->languages, 'iso2'),
                RichText::toPlain($content->text('labels', 'meta_title', $main)),
                RichText::toPlain($content->text('labels', 'meta_description', $main)),
            ),
        ];
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
