<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Media\InvalidImageException;
use BlazingSun\Api\Media\SvgSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SvgSanitizerTest extends TestCase
{
    public function testKeepsTheRealFlagsIntact(): void
    {
        foreach (['gb', 'fr', 'rs'] as $flag) {
            $source = (string) file_get_contents(dirname(__DIR__, 3) . "/images-src/flags/$flag.svg");
            $clean = SvgSanitizer::sanitize($source);
            // Same drawing elements before and after (the Serbian coat of arms has <style>, classes and <use>).
            foreach (['<path', '<circle', '<use', '<g'] as $element) {
                self::assertSame(substr_count($source, $element), substr_count($clean['svg'], $element), "$flag $element");
            }
            self::assertGreaterThan(0, $clean['width'], $flag);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function attacks(): iterable
    {
        yield 'script element' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect/></svg>', 'alert'];
        yield 'event handler' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect/></svg>', 'onload'];
        yield 'javascript link' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a href="javascript:alert(1)"><rect/></a><use xlink:href="https://evil.test/x.svg#a"/></svg>', 'evil'];
        yield 'foreignObject' => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe src="https://evil.test"/></foreignObject></svg>', 'iframe'];
        yield 'css import' => ['<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://evil.test/x.css);</style><rect style="fill:url(https://evil.test)"/></svg>', 'evil'];
        yield 'data uri' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/svg+xml;base64,PHN2Zz4="/></svg>', 'data:'];
    }

    #[DataProvider('attacks')]
    public function testRemovesEverythingThatCouldRunOrLoad(string $svg, string $mustBeGone): void
    {
        self::assertStringNotContainsString($mustBeGone, SvgSanitizer::sanitize($svg)['svg']);
    }

    public function testKeepsLocalReferences(): void
    {
        $clean = SvgSanitizer::sanitize('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 20"><defs><clipPath id="c"><rect width="30" height="20"/></clipPath></defs><path d="M0 0h30v20H0z" clip-path="url(#c)" fill="#012169"/></svg>');

        self::assertStringContainsString('clip-path="url(#c)"', $clean['svg']);
        self::assertSame(['width' => 30, 'height' => 20], ['width' => $clean['width'], 'height' => $clean['height']]);
    }

    /** @return iterable<string, array{string}> */
    public static function rejected(): iterable
    {
        yield 'entities (billion laughs)' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg">&a;</svg>'];
        yield 'not svg' => ['<html><body>hi</body></html>'];
        yield 'broken xml' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect>'];
    }

    #[DataProvider('rejected')]
    public function testRejectsDangerousOrInvalidFiles(string $svg): void
    {
        $this->expectException(InvalidImageException::class);
        SvgSanitizer::sanitize($svg);
    }
}
