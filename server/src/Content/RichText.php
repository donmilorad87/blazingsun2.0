<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

/**
 * Text that may contain only <em>…</em> and <b>…</b>. Everything is HTML-escaped first and then exactly those
 * four tags are restored, so no other markup or attribute can ever reach the page, whatever was stored.
 */
final class RichText
{
    private const array TAGS = ['<em>', '</em>', '<b>', '</b>'];

    public static function toHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $escapedTags = array_map(static fn (string $tag): string => htmlspecialchars($tag, ENT_QUOTES | ENT_HTML5, 'UTF-8'), self::TAGS);

        return str_replace($escapedTags, self::TAGS, $escaped);
    }

    /** Error message when the allowed tags are not properly opened, closed and nested; null when fine. */
    public static function nestingError(string $text): ?string
    {
        preg_match_all('#</?(?:em|b)>#', $text, $matches);
        $open = [];
        foreach ($matches[0] as $tag) {
            $name = trim($tag, '</>');
            if ($tag[1] !== '/') {
                $open[] = $name;
                continue;
            }
            if (array_pop($open) !== $name) {
                return "Unexpected $tag";
            }
        }

        return $open === [] ? null : 'Unclosed <' . end($open) . '>';
    }

    /** Plain text: the allowed tags removed (for <title>, meta and JSON-LD). */
    public static function toPlain(string $text): string
    {
        return str_replace(self::TAGS, '', $text);
    }
}
