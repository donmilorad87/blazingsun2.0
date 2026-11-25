<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

/**
 * <picture> markup for a media row: a WebP <source> plus a fallback <img> (PNG or the original GIF), like the
 * hand-written markup it replaces; an SVG is a plain <img> inside <picture> (so CSS and JS see the same structure).
 *
 * @phpstan-import-type Media from \BlazingSun\Api\Content\Content
 */
final class Picture
{
    /**
     * @param Media|null $media
     * @param array<string, string> $attributes extra <img> attributes, in output order
     */
    public static function html(?array $media, string $sizes, array $attributes): string
    {
        if ($media === null) {
            return '';
        }
        $img = self::attributes($attributes);
        if ($media['kind'] === 'svg') {
            return sprintf('<picture><img src="%s"%s></picture>', self::escape($media['path'] . '.svg'), $img);
        }

        $variants = $media['variants'];
        $fallback = is_string($variants['fallback'] ?? null) ? $variants['fallback'] : 'png';
        $webpWidths = self::widths($variants['webp'] ?? []);
        $fallbackWidths = self::widths($variants['fallbackWidths'] ?? []);
        $largest = $fallbackWidths === [] ? $media['width'] : max($fallbackWidths);
        $source = $webpWidths === [] ? '' : sprintf('<source type="image/webp" srcset="%s" sizes="%s">', self::srcset($media['path'], $webpWidths, 'webp'), self::escape($sizes));

        return sprintf(
            '<picture>%s<img src="%s" srcset="%s" sizes="%s"%s></picture>',
            $source,
            self::escape("{$media['path']}-$largest.$fallback"),
            self::srcset($media['path'], $fallbackWidths, $fallback),
            self::escape($sizes),
            $img,
        );
    }

    /**
     * One small file of the image, for thumbnails in the admin portal.
     *
     * @param Media $media
     */
    public static function previewUrl(array $media): string
    {
        if ($media['kind'] === 'svg') {
            return $media['path'] . '.svg';
        }
        $fallback = is_string($media['variants']['fallback'] ?? null) ? $media['variants']['fallback'] : 'png';
        $widths = self::widths($media['variants']['fallbackWidths'] ?? []);
        $small = array_filter($widths, static fn (int $width): bool => $width <= 135);
        $width = $small !== [] ? max($small) : ($widths === [] ? $media['width'] : min($widths));

        return "{$media['path']}-$width.$fallback";
    }

    /**
     * "/path-24.webp 24w, /path-48.webp 48w" — same format as srcset() in src/js/imageSets.js.
     *
     * @param list<int> $widths
     */
    public static function srcset(string $path, array $widths, string $format): string
    {
        return self::escape(implode(', ', array_map(static fn (int $width): string => "$path-$width.$format {$width}w", $widths)));
    }

    /** @param array<string, string> $attributes */
    private static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', $name, self::escape($value));
        }

        return $html;
    }

    /** @return list<int> */
    private static function widths(mixed $widths): array
    {
        return is_array($widths) ? array_values(array_filter($widths, is_int(...))) : [];
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
