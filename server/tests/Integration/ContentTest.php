<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Integration;

use BlazingSun\Api\Content\Content;
use BlazingSun\Api\Content\ContentEditor;
use BlazingSun\Api\Content\ContentSeeder;
use BlazingSun\Api\Content\ValidationException;
use BlazingSun\Api\Models\ContentRepository;

final class ContentTest extends DatabaseTestCase
{
    private ContentRepository $repository;

    private int $changes = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContentRepository($this->connection);
        (new ContentSeeder($this->repository, dirname(__DIR__, 2) . '/content/seed.json'))->run();
    }

    private function editor(): ContentEditor
    {
        return new ContentEditor($this->repository, function (): void {
            $this->changes++;
        });
    }

    public function testSeedRestoresTheOriginalPageOnce(): void
    {
        $content = $this->repository->snapshot();

        self::assertSame(['sr', 'en', 'fr'], array_column($content->languages, 'iso2'));
        self::assertSame('en', $content->mainLanguage());
        self::assertSame('Gradimo <em>digitalne</em> sisteme', $content->text('section:hero', 'title', 'sr'));
        self::assertCount(6, $content->itemIds('stack', 'items'));
        self::assertCount(4, $content->itemIds('contact', 'items'));
        self::assertFalse($content->nav['cv']);
        self::assertSame('raster', $content->image('header', 'logo_dark')['kind'] ?? null);
        self::assertSame([], ContentEditor::missingRequired($content, 'en'), 'the main language is complete');
        self::assertContains('header.name_dark', ContentEditor::missingRequired($content, 'sr'));

        self::assertFalse((new ContentSeeder($this->repository, dirname(__DIR__, 2) . '/content/seed.json'))->run());
        self::assertCount(6, $this->repository->snapshot()->itemIds('stack', 'items'));
    }

    public function testSavingASectionCreatesUpdatesDeletesAndReordersItems(): void
    {
        $before = $this->repository->snapshot();
        [$first, $second] = $before->itemIds('hero', 'items');

        $this->editor()->saveSection('hero', [
            'values' => [
                'before_title' => ['en' => 'Engineer'],
                'title' => ['en' => 'New <em>title</em>', 'sr' => 'Novi naslov'],
                'description' => ['en' => 'Lead'],
                'info' => ['en' => ''],
            ],
            'groups' => ['items' => [
                ['values' => ['label' => ['en' => 'Brand new'], 'href' => 'https://example.com', 'style' => 'ghost']],
                ['id' => $second, 'values' => ['label' => ['en' => 'Projects'], 'href' => '#projects', 'style' => 'ghost']],
            ]],
        ]);

        $after = $this->repository->snapshot();
        $ids = $after->itemIds('hero', 'items');
        self::assertCount(2, $ids);
        self::assertSame($second, $ids[1]);
        self::assertNotContains($first, $ids);
        self::assertSame('Brand new', $after->text(Content::itemScope($ids[0]), 'label', 'fr'), 'falls back to English');
        self::assertSame('Novi naslov', $after->text('section:hero', 'title', 'sr'));
        self::assertSame('', $after->text('section:hero', 'info', 'sr'), 'emptied value is deleted everywhere it was empty');
        self::assertSame(1, $this->changes);
    }

    public function testInvalidSectionIsRejectedWithoutWritingAnything(): void
    {
        $before = $this->repository->snapshot();
        try {
            $this->editor()->saveSection('stack', [
                'values' => ['before_title' => ['en' => 'x'], 'title' => ['en' => ''], 'nav_label' => ['en' => 'Stack']],
                'groups' => ['items' => []],
            ]);
            self::fail('expected a validation error');
        } catch (ValidationException $error) {
            self::assertSame(['values.title.en' => 'Required in the main language'], $error->errors);
        }
        self::assertEquals($before, $this->repository->snapshot());
        self::assertSame(0, $this->changes);
    }

    public function testItemIdsFromAnotherSectionAreRejected(): void
    {
        $foreign = $this->repository->snapshot()->itemIds('stack', 'items')[0];

        $this->expectException(ValidationException::class);
        $this->editor()->saveSection('cv', ['values' => [], 'groups' => ['items' => [['id' => $foreign, 'values' => []]]]]);
    }

    public function testLanguagesCanBeAddedMadeMainAndDeleted(): void
    {
        $flag = $this->repository->createMedia('svg', '/uploads/media/de', ['svg' => true], 5, 3);
        $editor = $this->editor();

        $editor->saveLanguage(['iso2' => 'DE', 'iso3' => 'deu', 'name' => 'Deutsch', 'flag' => $flag]);
        $content = $this->repository->snapshot();
        self::assertSame(['sr', 'en', 'fr', 'de'], array_column($content->languages, 'iso2'));
        self::assertSame('Blazing Sun', $content->text('header', 'name_light', 'de'), 'new language falls back to English');

        try {
            $editor->setMainLanguage('de');
            self::fail('an untranslated language cannot become main');
        } catch (ValidationException $error) {
            self::assertStringStartsWith('Translate the ', $error->errors['iso2']);
        }

        foreach (ContentEditor::missingRequired($this->repository->snapshot(), 'sr') as $path) {
            [$scope, $field] = explode('.', $path);
            $this->repository->setValue($scope, $field, 'sr', 'prevod');
        }
        $editor->setMainLanguage('sr');
        self::assertSame('sr', $this->repository->snapshot()->mainLanguage());

        try {
            $editor->deleteLanguage('sr');
            self::fail('the main language cannot be deleted');
        } catch (ValidationException $error) {
            self::assertSame(['iso2' => 'Choose another main language first'], $error->errors);
        }

        $editor->deleteLanguage('fr');
        $content = $this->repository->snapshot();
        self::assertNull($content->language('fr'));
        self::assertFalse($content->isTranslated('section:hero', 'title', 'fr'));
    }

    public function testDuplicateIso3IsAFieldError(): void
    {
        $this->expectExceptionObject(ValidationException::single('iso3', 'Already used by another language'));
        $this->editor()->saveLanguage(['iso2' => 'xx', 'iso3' => 'eng', 'name' => 'Fake', 'flag' => 1]);
    }

    public function testMenuShowsHidesAndRenamesSections(): void
    {
        $this->editor()->saveMenu(['items' => [
            'cv' => ['in_nav' => true, 'label' => ['en' => 'CV', 'sr' => 'Biografija']],
            'stack' => ['in_nav' => false, 'label' => ['en' => 'Stack']],
        ]]);

        $content = $this->repository->snapshot();
        self::assertTrue($content->nav['cv']);
        self::assertFalse($content->nav['stack']);
        self::assertSame('Biografija', $content->text('section:cv', 'nav_label', 'sr'));
    }

    public function testSettingsAreValidatedPerScope(): void
    {
        $this->editor()->saveSettings('footer', ['values' => ['name' => 'Milorad', 'company' => 'Blazing Sun', 'position' => ['en' => 'Engineer'], 'country' => ['en' => 'Serbia']]]);
        self::assertSame('Engineer', $this->repository->snapshot()->text('footer', 'position', 'fr'));

        $this->expectException(ValidationException::class);
        $this->editor()->saveSettings('secrets', ['values' => []]);
    }
}
