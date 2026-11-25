<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Content\Definitions;
use BlazingSun\Api\Content\ValidationException;
use BlazingSun\Api\Content\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /** @return list<array{name: string, label: string, type: string, required: bool, options?: list<string>}> */
    private function buttonFields(): array
    {
        return Definitions::sections()['hero']['groups']['items']['fields'];
    }

    public function testValidButtonBecomesWritesForEveryLanguage(): void
    {
        $validator = new Validator(ContentFixture::make());
        $writes = $validator->fields($this->buttonFields(), ['label' => ['en' => ' Hire me ', 'sr' => ''], 'href' => '#contact', 'style' => 'primary'], 'item');

        self::assertSame([], $validator->errors());
        self::assertSame([
            ['field' => 'label', 'lang' => 'en', 'value' => 'Hire me'],
            ['field' => 'label', 'lang' => 'sr', 'value' => ''],
            ['field' => 'href', 'lang' => '', 'value' => '#contact'],
            ['field' => 'style', 'lang' => '', 'value' => 'primary'],
        ], $writes);
    }

    public function testCollectsEveryError(): void
    {
        $validator = new Validator(ContentFixture::make());
        $validator->fields($this->buttonFields(), ['label' => ['sr' => 'Samo srpski', 'de' => 'x'], 'href' => 'javascript:alert(1)', 'style' => 'loud', 'evil' => 'x'], 'item');

        self::assertSame([
            'item.evil' => 'Unknown field',
            'item.label.de' => 'Unknown language',
            'item.label.en' => 'Required in the main language',
            'item.href' => 'Use https://…, http://…, mailto:…, tel:… or #section',
            'item.style' => 'Choose one of: primary, ghost',
        ], $validator->errors());
        $this->expectException(ValidationException::class);
        $validator->assertValid();
    }

    public function testRichTextMustBeProperlyNested(): void
    {
        $validator = new Validator(ContentFixture::make());
        $fields = Definitions::sections()['stack']['groups']['items']['fields'];
        $validator->fields($fields, ['title' => ['en' => 'T'], 'text' => ['en' => '<b>open']], 'card');

        self::assertSame(['card.text.en' => 'Unclosed <b>'], $validator->errors());
    }

    public function testImagesMustExistAndTextHasLimits(): void
    {
        $validator = new Validator(ContentFixture::make());
        $fields = Definitions::settings()['header'];
        $validator->fields($fields, [
            'name_dark' => ['en' => str_repeat('x', Validator::MAX_TEXT_LENGTH + 1)],
            'name_light' => ['en' => 'Sun'],
            'logo_dark' => '3', 'logo_light' => 99, 'icon_dark' => 'abc', 'icon_light' => ['en' => '1'],
        ], 'header');

        self::assertSame([
            'header.name_dark.en' => 'At most 5000 characters',
            'header.logo_light' => 'Unknown image',
            'header.icon_dark' => 'Unknown image',
            'header.icon_light' => 'Expected text',
        ], $validator->errors());
    }

    public function testUrls(): void
    {
        foreach (['https://github.com/x', 'http://a.b', 'mailto:me@x.com', 'tel:+381 62 961', '#contact'] as $url) {
            self::assertTrue(Validator::isUrl($url), $url);
        }
        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.com', 'https://a b', '#', 'mailto:nobody'] as $url) {
            self::assertFalse(Validator::isUrl($url), $url);
        }
    }

    public function testNonObjectInputIsAnError(): void
    {
        $validator = new Validator(ContentFixture::make());
        $validator->fields($this->buttonFields(), 'nope', 'item');

        self::assertSame(['item' => 'Expected an object of field values'], $validator->errors());
    }
}
