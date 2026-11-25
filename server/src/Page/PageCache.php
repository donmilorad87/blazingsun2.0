<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

use RuntimeException;

/**
 * Rendered pages on disk, one file per language. The key includes the asset version (a deploy changes the hashed
 * CSS/JS names) and the year (the footer's ©), so those never serve stale HTML; any admin save calls clear().
 */
final readonly class PageCache
{
    public function __construct(private string $directory)
    {
    }

    /** Cached HTML for a URL key ("main" for /, else the iso2 of /{iso2}/), or null. */
    public function read(string $key, string $version, int $year): ?string
    {
        $file = $this->file($key, $version, $year);
        $html = is_file($file) ? file_get_contents($file) : false;

        return $html === false ? null : $html;
    }

    public function store(string $key, string $version, int $year, string $html): void
    {
        $this->write($this->file($key, $version, $year), $html);
    }

    public function clear(): void
    {
        $files = glob($this->directory . '/page-*.html');
        foreach ($files === false ? [] : $files as $file) {
            if (!unlink($file) && is_file($file)) {
                throw new RuntimeException("Cannot delete cached page $file");
            }
        }
    }

    private function file(string $key, string $version, int $year): string
    {
        assert(preg_match('/^(main|[a-z]{2})$/', $key) === 1, 'cache key is "main" or an iso2 code');

        return sprintf('%s/page-%s-%s-%d.html', $this->directory, $key, preg_replace('/[^a-z0-9]/i', '', $version), $year);
    }

    /** Atomic write (temp file + rename), so a visitor never gets half a page. A failed write only logs: the page still renders. */
    private function write(string $file, string $html): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            error_log("[page] cannot create cache directory {$this->directory}");
            return;
        }
        $temp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temp, $html) === false || !rename($temp, $file)) {
            error_log("[page] cannot write cache file $file");
            @unlink($temp);
        }
    }
}
