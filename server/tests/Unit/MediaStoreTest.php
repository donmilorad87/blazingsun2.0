<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Media\MediaStore;
use PHPUnit\Framework\TestCase;

final class MediaStoreTest extends TestCase
{
    public function testWidthsNeverUpscale(): void
    {
        self::assertSame(MediaStore::WIDTHS, MediaStore::widthsFor(1200));
        self::assertSame(MediaStore::WIDTHS, MediaStore::widthsFor(426));
        self::assertSame([24, 48, 72, 90, 135, 200], MediaStore::widthsFor(200));
        self::assertSame([16], MediaStore::widthsFor(16));
    }

    public function testDetectsSvgByContent(): void
    {
        self::assertTrue(MediaStore::looksLikeSvg("<?xml version=\"1.0\"?>\n<!-- made by hand -->\n<svg xmlns=\"http://www.w3.org/2000/svg\"/>"));
        self::assertTrue(MediaStore::looksLikeSvg('  <svg viewBox="0 0 1 1"></svg>'));
        self::assertFalse(MediaStore::looksLikeSvg("\x89PNG\r\n"));
        self::assertFalse(MediaStore::looksLikeSvg('<html><svg></svg></html>'));
    }

    public function testDetectsAnimatedGifs(): void
    {
        $root = dirname(__DIR__, 3);
        self::assertTrue(MediaStore::isAnimated((string) file_get_contents("$root/images-src/sun.gif")));

        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagegif($image);
        self::assertFalse(MediaStore::isAnimated((string) ob_get_clean()));
        self::assertTrue(MediaStore::isAnimatedWebp((string) file_get_contents("$root/public/images/responsive/sun-426.webp")));
        self::assertFalse(MediaStore::isAnimatedWebp((string) file_get_contents("$root/public/images/responsive/moon-426.webp")));
    }
}
