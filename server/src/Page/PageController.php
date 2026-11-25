<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

use BlazingSun\Api\Content\Content;
use Closure;

/**
 * Serves / (main language) and /{iso2}/ (the others). A cached page is returned without touching the database;
 * otherwise the content is loaded, rendered and cached. /{main}/ redirects to /, unknown paths are 404.
 *
 * @phpstan-type PageResponse array{status: int, headers: array<string, string>, body: string}
 */
final readonly class PageController
{
    /**
     * @param Closure(): Content $content
     * @param Closure(): Assets $assets
     */
    public function __construct(
        private Closure $content,
        private Closure $assets,
        private PageCache $cache,
        private PageRenderer $renderer,
        private string $siteUrl,
        private int $year,
    ) {
    }

    /** @return PageResponse */
    public function handle(string $path): array
    {
        if (preg_match('~^/(?:([a-z]{2})/?)?$~', $path, $match) !== 1) {
            return self::notFound();
        }
        $key = $match[1] ?? '';
        $key = $key === '' ? 'main' : $key;
        $assets = ($this->assets)();

        $html = $this->cache->read($key, $assets->version, $this->year);
        if ($html !== null) {
            return self::page($html);
        }

        $content = ($this->content)();
        $main = $content->mainLanguage();
        if ($key === $main) {
            return ['status' => 301, 'headers' => ['Location' => '/', 'Cache-Control' => 'no-cache'], 'body' => ''];
        }
        $lang = $key === 'main' ? $main : $key;
        if ($content->language($lang) === null) {
            return self::notFound();
        }

        $html = $this->renderer->render(new PageView($content, $lang, $assets, $this->siteUrl, $this->year));
        $this->cache->store($key, $assets->version, $this->year, $html);

        return self::page($html);
    }

    /** @return PageResponse */
    private static function page(string $html): array
    {
        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'text/html; charset=utf-8',
                // Always revalidate: an admin save must show up on the next load.
                'Cache-Control' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
            ],
            'body' => $html,
        ];
    }

    /** @return PageResponse */
    private static function notFound(): array
    {
        return [
            'status' => 404,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache'],
            'body' => "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"utf-8\"><title>Not found</title></head><body><p>Page not found. <a href=\"/\">Go to the home page</a>.</p></body></html>\n",
        ];
    }
}
