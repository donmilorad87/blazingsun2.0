<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase
{
    public function testTranslationFallsBackToTheMainLanguage(): void
    {
        $content = ContentFixture::make(['section:hero' => ['title' => ['en' => 'Hello', 'sr' => 'Zdravo'], 'info' => ['en' => 'Only English']]]);

        self::assertSame('Zdravo', $content->text('section:hero', 'title', 'sr'));
        self::assertSame('Only English', $content->text('section:hero', 'info', 'sr'));
        self::assertSame('', $content->text('section:hero', 'missing', 'sr'));
        self::assertTrue($content->isTranslated('section:hero', 'title', 'sr'));
        self::assertFalse($content->isTranslated('section:hero', 'info', 'sr'));
    }

    public function testMainLanguageAndLookups(): void
    {
        $content = ContentFixture::make(['header' => ['logo_dark' => ['' => '3'], 'logo_light' => ['' => 'nope']]]);

        self::assertSame('en', $content->mainLanguage());
        self::assertSame('Srpski', $content->language('sr')['name'] ?? null);
        self::assertNull($content->language('de'));
        self::assertSame([7], $content->itemIds('hero', 'items'));
        self::assertSame([], $content->itemIds('about', 'items'));
        self::assertSame('animated', $content->image('header', 'logo_dark')['kind'] ?? null);
        self::assertNull($content->image('header', 'logo_light'));
    }
}
