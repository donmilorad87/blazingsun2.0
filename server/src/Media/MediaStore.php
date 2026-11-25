<?php

declare(strict_types=1);

namespace BlazingSun\Api\Media;

use BlazingSun\Api\Models\ContentRepository;
use GdImage;
use RuntimeException;

/**
 * Turns an uploaded file into a media row plus files under {uploads}/media/{random}…:
 * - PNG/JPEG/WebP → resized with GD to the same widths as the built-in images (WebP + PNG fallback),
 * - animated GIF / WebP → kept as it is (GD would drop the animation),
 * - SVG → cleaned by SvgSanitizer.
 * Purpose "flag" only accepts SVG (language flags).
 *
 * @phpstan-type Stored array{id: int, kind: string, path: string, variants: array<string, mixed>, width: int, height: int}
 */
final readonly class MediaStore
{
    /** Animated hero images are big (the original sun GIF is 4 MB). */
    public const int MAX_BYTES = 8 * 1024 * 1024;

    /** Same widths as the sun/moon images (src/js/imageSets.js): 24px toggle up to the 426px hero orb. */
    public const array WIDTHS = [24, 48, 72, 90, 135, 240, 330, 426];

    private const int MAX_PIXELS = 4096 * 4096;

    public function __construct(
        private ContentRepository $repository,
        private string $uploadsDirectory,
    ) {
    }

    /** @return Stored */
    public function store(string $file, string $purpose): array
    {
        if (!in_array($purpose, ['image', 'flag'], true)) {
            throw new InvalidImageException('Unknown upload purpose');
        }
        $size = is_file($file) ? filesize($file) : false;
        if ($size === false || $size === 0) {
            throw new InvalidImageException('The upload is empty');
        }
        if ($size > self::MAX_BYTES) {
            throw new InvalidImageException('Images must be 8 MB or smaller');
        }
        $data = (string) file_get_contents($file);
        $name = bin2hex(random_bytes(12));
        $directory = $this->directory();

        if (self::looksLikeSvg($data)) {
            $clean = SvgSanitizer::sanitize($data);
            $this->write("$directory/$name.svg", $clean['svg']);

            return $this->save('svg', $name, ['svg' => true], $clean['width'], $clean['height']);
        }
        if ($purpose === 'flag') {
            throw new InvalidImageException('Flags must be SVG files');
        }

        $info = getimagesizefromstring($data);
        if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new InvalidImageException('Upload a PNG, JPEG, WebP, GIF or SVG image');
        }
        [$width, $height] = [$info[0], $info[1]];
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new InvalidImageException('Images can be at most 4096 × 4096 pixels');
        }

        if ($info[2] === IMAGETYPE_GIF && self::isAnimated($data)) {
            $this->write("$directory/$name-$width.gif", $data);

            return $this->save('animated', $name, ['webp' => [], 'fallback' => 'gif', 'fallbackWidths' => [$width]], $width, $height);
        }
        if ($info[2] === IMAGETYPE_WEBP && self::isAnimatedWebp($data)) {
            $this->write("$directory/$name-$width.webp", $data);

            return $this->save('animated', $name, ['webp' => [], 'fallback' => 'webp', 'fallbackWidths' => [$width]], $width, $height);
        }

        return $this->save('raster', $name, $this->resize($data, "$directory/$name", $width, $height), $width, $height);
    }

    /** @return array<string, mixed> variants of the files written */
    private function resize(string $data, string $base, int $width, int $height): array
    {
        $source = imagecreatefromstring($data);
        if (!$source instanceof GdImage) {
            throw new InvalidImageException('The image could not be read');
        }
        imagepalettetotruecolor($source);
        $widths = self::widthsFor($width);
        $webp = function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0;
        foreach ($widths as $target) {
            $resized = imagescale($source, $target, max(1, (int) round($height * $target / $width)), IMG_BICUBIC);
            if (!$resized instanceof GdImage) {
                throw new RuntimeException("Could not resize the image to {$target}px");
            }
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            if (!imagepng($resized, "$base-$target.png", 9) || ($webp && !imagewebp($resized, "$base-$target.webp", 82))) {
                throw new RuntimeException("Could not write $base-$target");
            }
        }

        // shortcut: hosts whose GD lacks WebP get PNG only; /api/health reports it (webp: false).
        return ['webp' => $webp ? $widths : [], 'fallback' => 'png', 'fallbackWidths' => $widths];
    }

    /** @return list<int> the standard widths up to the image's own width (never upscaled) */
    public static function widthsFor(int $width): array
    {
        $widths = array_values(array_filter(self::WIDTHS, static fn (int $target): bool => $target <= $width));
        if ($width < max(self::WIDTHS) && !in_array($width, $widths, true)) {
            $widths[] = $width;
        }

        return $widths;
    }

    public static function looksLikeSvg(string $data): bool
    {
        return preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', substr($data, 0, 4096)) === 1;
    }

    /** More than one frame (graphic control extension followed by an image descriptor). */
    public static function isAnimated(string $gif): bool
    {
        return preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $gif) > 1;
    }

    /** An extended WebP (VP8X) with the animation flag set. */
    public static function isAnimatedWebp(string $webp): bool
    {
        return substr($webp, 12, 4) === 'VP8X' && (ord($webp[20] ?? "\0") & 0x02) !== 0;
    }

    /**
     * @param array<string, mixed> $variants
     * @return Stored
     */
    private function save(string $kind, string $name, array $variants, int $width, int $height): array
    {
        $path = "/uploads/media/$name";
        $id = $this->repository->createMedia($kind, $path, $variants, $width, $height);

        return ['id' => $id, 'kind' => $kind, 'path' => $path, 'variants' => $variants, 'width' => $width, 'height' => $height];
    }

    private function directory(): string
    {
        $directory = $this->uploadsDirectory . '/media';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create the uploads directory $directory");
        }

        return $directory;
    }

    private function write(string $file, string $data): void
    {
        if (file_put_contents($file, $data) === false) {
            throw new RuntimeException("Cannot write $file");
        }
    }
}
