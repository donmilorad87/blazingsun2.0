<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Content\RichText;
use PHPUnit\Framework\TestCase;

final class RichTextTest extends TestCase
{
    public function testKeepsOnlyEmAndB(): void
    {
        self::assertSame('Building <em>digital</em> &amp; <b>fast</b>', RichText::toHtml('Building <em>digital</em> & <b>fast</b>'));
    }

    public function testEscapesEverythingElse(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', RichText::toHtml('<script>alert(1)</script>'));
        self::assertStringNotContainsString('<em onclick', RichText::toHtml('<em onclick="x">hi</em>'));
        self::assertStringNotContainsString('<img', RichText::toHtml('<img src=x onerror=alert(1)>'));
    }

    public function testNestingErrors(): void
    {
        self::assertNull(RichText::nestingError('<b>a <em>b</em></b> plain'));
        self::assertSame('Unclosed <em>', RichText::nestingError('<em>open'));
        self::assertSame('Unexpected </b>', RichText::nestingError('<em>x</b>'));
        self::assertSame('Unexpected </em>', RichText::nestingError('x</em>'));
    }

    public function testPlainStripsTheTags(): void
    {
        self::assertSame('Building digital systems', RichText::toPlain('Building <em>digital</em> systems'));
    }
}
