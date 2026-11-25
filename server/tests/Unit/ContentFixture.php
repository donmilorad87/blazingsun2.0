<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Content\Content;

/** Small in-memory content: en (main) + sr, one hero button, one image. */
final class ContentFixture
{
    /** @param array<string, array<string, array<string, string>>> $values */
    public static function make(array $values = []): Content
    {
        return new Content(
            [
                ['iso2' => 'en', 'iso3' => 'eng', 'name' => 'English', 'flag_media_id' => 1, 'is_main' => true],
                ['iso2' => 'sr', 'iso3' => 'srp', 'name' => 'Srpski', 'flag_media_id' => 2, 'is_main' => false],
            ],
            ['hero' => false, 'about' => true, 'stack' => true, 'experience' => true, 'projects' => true, 'cv' => false, 'contact' => true],
            [['id' => 7, 'section' => 'hero', 'group' => 'items']],
            $values,
            [
                1 => ['id' => 1, 'kind' => 'raster', 'path' => '/images/responsive/flag-gb', 'variants' => ['webp' => [24, 48], 'fallback' => 'png', 'fallbackWidths' => [24, 48]], 'width' => 72, 'height' => 36],
                2 => ['id' => 2, 'kind' => 'svg', 'path' => '/uploads/media/abc', 'variants' => ['svg' => true], 'width' => 30, 'height' => 20],
                3 => ['id' => 3, 'kind' => 'animated', 'path' => '/images/responsive/sun', 'variants' => ['webp' => [24, 48], 'fallback' => 'gif', 'fallbackWidths' => [426]], 'width' => 426, 'height' => 426],
            ],
        );
    }
}
