<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

use RuntimeException;

/**
 * The <head> tags that load the Vite bundle for one entry: from dist/asset-manifest.json in production, or from
 * the Vite dev server while `npm run dev` runs (it writes its URL to the "hot" file and removes it on exit).
 */
final readonly class Assets
{
    /**
     * @param list<string> $css
     * @param list<string> $js
     */
    private function __construct(
        public array $css,
        public array $js,
        public string $version,
    ) {
    }

    public static function load(string $manifestFile, string $hotFile, string $entry): self
    {
        if (is_file($hotFile)) {
            $server = rtrim(trim((string) file_get_contents($hotFile)), '/');
            if (preg_match('~^https?://[a-z0-9.:\[\]-]+$~i', $server) === 1) {
                return new self([], ["$server/@vite/client", "$server/$entry"], 'dev');
            }
        }

        $json = is_file($manifestFile) ? file_get_contents($manifestFile) : false;
        if ($json === false) {
            throw new RuntimeException("Asset manifest not found: $manifestFile (run npm run build)");
        }
        $manifest = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        $chunk = is_array($manifest) ? ($manifest[$entry] ?? null) : null;
        if (!is_array($chunk) || !is_string($chunk['file'] ?? null)) {
            throw new RuntimeException("Entry $entry is missing from $manifestFile");
        }
        $css = array_values(array_filter($chunk['css'] ?? [], is_string(...)));

        return new self(
            array_map(static fn (string $file): string => "/$file", $css),
            ['/' . $chunk['file']],
            substr(hash('sha256', $json), 0, 12),
        );
    }
}
