<?php

declare(strict_types=1);

namespace BlazingSun\Api\Media;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans an uploaded SVG down to drawing elements and attributes, so it can never run script — even when opened
 * directly (uploads are also served with a sandbox CSP, as a second layer).
 * Removes: DOCTYPE/entities (rejected outright), unknown or foreign elements (script, foreignObject, metadata…),
 * event handlers (on*), links that leave the document, and CSS that loads anything (url(), @import, expression).
 */
final class SvgSanitizer
{
    public const int MAX_BYTES = 512 * 1024;

    private const int MAX_NODES = 20000;

    private const array ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'defs', 'use',
        'symbol', 'title', 'desc', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'style',
    ];

    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /** @return array{svg: string, width: int, height: int} the cleaned SVG and its intrinsic size (0 when unknown) */
    public static function sanitize(string $svg): array
    {
        if (strlen($svg) > self::MAX_BYTES) {
            throw new InvalidImageException('The SVG is larger than 512 KB');
        }
        if (trim($svg) === '') {
            throw new InvalidImageException('The SVG is empty');
        }
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg) === 1) {
            throw new InvalidImageException('SVGs with a DOCTYPE or entities are not accepted');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->documentElement;
        if (!$loaded || $root === null || strtolower($root->localName ?? '') !== 'svg') {
            throw new InvalidImageException('Not a valid SVG file');
        }

        self::clean($root);
        $output = $document->saveXML($root);
        if ($output === false) {
            throw new InvalidImageException('Could not save the cleaned SVG');
        }

        return ['svg' => $output, ...self::size($root)];
    }

    /** Walks the tree with an explicit stack (no recursion), removing what isn't allowed. */
    private static function clean(DOMElement $root): void
    {
        $stack = [$root];
        $visited = 0;
        while ($stack !== [] && $visited++ < self::MAX_NODES) {
            $node = array_pop($stack);
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof DOMElement) {
                    if (self::isAllowedElement($child)) {
                        $stack[] = $child;
                        continue;
                    }
                    $node->removeChild($child);
                } elseif (in_array($child->nodeType, [XML_PI_NODE, XML_COMMENT_NODE], true)) {
                    $node->removeChild($child);
                }
            }
            self::cleanAttributes($node);
            if (strtolower($node->localName ?? '') === 'style' && !self::isSafeCss($node->textContent ?? '')) {
                $node->parentNode?->removeChild($node);
            }
        }
        if ($visited > self::MAX_NODES) {
            throw new InvalidImageException('The SVG is too complex');
        }
    }

    private static function isAllowedElement(DOMElement $element): bool
    {
        $namespace = $element->namespaceURI;

        return ($namespace === self::SVG_NAMESPACE || $namespace === null)
            && in_array(strtolower($element->localName ?? ''), self::ELEMENTS, true);
    }

    private static function cleanAttributes(DOMNode $element): void
    {
        if (!$element instanceof DOMElement) {
            return;
        }
        /** @var list<DOMAttr> $attributes */
        $attributes = iterator_to_array($element->attributes ?? []);
        foreach ($attributes as $attribute) {
            if (!self::isAllowedAttribute($attribute)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private static function isAllowedAttribute(DOMAttr $attribute): bool
    {
        $name = strtolower($attribute->nodeName);
        $value = trim($attribute->value);
        if (str_starts_with($name, 'on') || preg_match('/^[a-z][a-z0-9:-]*$/', $name) !== 1) {
            return false;
        }
        if ($name === 'href' || $name === 'xlink:href') {
            return str_starts_with($value, '#');
        }
        if (str_contains($name, ':') && !in_array($name, ['xml:space', 'xmlns:xlink'], true)) {
            return false;
        }
        if (preg_match('/javascript:|data:|vbscript:/i', $value) === 1) {
            return false;
        }

        return $name !== 'style' || self::isSafeCss($value);
    }

    /** CSS may only reference things inside the SVG: url(#id) is fine, anything else that loads or runs is not. */
    private static function isSafeCss(string $css): bool
    {
        return preg_match('/@import|expression\s*\(|javascript:|behavior\s*:|-moz-binding|<|url\s*\(\s*["\']?\s*(?!#)/i', $css) !== 1;
    }

    /** @return array{width: int, height: int} */
    private static function size(DOMElement $root): array
    {
        $width = (int) round((float) $root->getAttribute('width'));
        $height = (int) round((float) $root->getAttribute('height'));
        if (($width <= 0 || $height <= 0) && preg_match('/^\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/', $root->getAttribute('viewBox'), $box) === 1) {
            [$width, $height] = [(int) round((float) $box[1]), (int) round((float) $box[2])];
        }

        return ['width' => max(0, $width), 'height' => max(0, $height)];
    }
}
