<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Generates responsive WebP variants for locally-stored images.
 *
 * Variants are written *alongside* the original on the `public` disk
 * (e.g. uploads/x.jpg → uploads/x-480.webp, uploads/x-960.webp). The original
 * file is never renamed or replaced, so every existing DB image reference keeps
 * resolving and the original stays as the <img src> fallback. Generation is
 * idempotent: existing variants are skipped unless $force is set.
 *
 * Decoding a large image with GD is memory-hungry, so generation is guarded:
 * it raises the memory limit when allowed and otherwise skips the image (the
 * original is still served), so an upload or backfill never fatals.
 */
class ImageVariants
{
    /** Nominal widths (px) for responsive WebP variants. */
    public const WIDTHS = [480, 960, 1600];

    /** WebP encode quality (0-100). */
    private const QUALITY = 80;

    /** Source extensions we generate variants for (animated gif excluded). */
    public const SOURCE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function supported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /**
     * Whether a path is itself a generated variant (e.g. x-480.webp). Variants
     * must never be treated as sources, or x-480.webp would spawn x-480-480.webp.
     */
    public static function isVariant(string $relPath): bool
    {
        return (bool) preg_match('/-(?:' . implode('|', self::WIDTHS) . ')\.webp$/i', $relPath);
    }

    /**
     * Generate WebP variants for a public-disk image path (e.g. "uploads/x.jpg").
     *
     * @return string[] relative paths of variants that now exist
     */
    public static function generate(string $relPath, bool $force = false): array
    {
        $disk = Storage::disk('public');

        if (! self::supported() || ! $disk->exists($relPath)) {
            return [];
        }

        $info = pathinfo($relPath);
        $ext  = strtolower($info['extension'] ?? '');
        if (! in_array($ext, self::SOURCE_EXTENSIONS, true) || self::isVariant($relPath)) {
            return [];
        }

        $dir  = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';
        $name = $info['filename'];

        // Plan the variant paths and find which are missing — cheap stat calls.
        $planned = [];
        $missing = [];
        foreach (self::WIDTHS as $w) {
            $variant       = "{$dir}{$name}-{$w}.webp";
            $planned[$w]   = $variant;
            if ($force || ! $disk->exists($variant)) {
                $missing[$w] = $variant;
            }
        }

        // Fast path: everything already generated, no file read or decode needed.
        if ($missing === []) {
            return array_values($planned);
        }

        $raw  = $disk->get($relPath);
        $dims = @getimagesizefromstring($raw);
        if ($dims === false || ($dims[0] ?? 0) <= 0 || ($dims[1] ?? 0) <= 0) {
            return self::existing($disk, $planned);
        }

        [$ow, $oh] = $dims;

        // Bail (serving the original) rather than risk a fatal OOM.
        if (! self::ensureMemory($ow, $oh)) {
            return self::existing($disk, $planned);
        }

        $src = @imagecreatefromstring($raw);
        unset($raw);
        if ($src === false) {
            return self::existing($disk, $planned);
        }

        $created = [];
        foreach (self::WIDTHS as $w) {
            $variant = $planned[$w];

            if (isset($missing[$w])) {
                $tw  = min($w, $ow);
                $th  = max(1, (int) round($oh * ($tw / $ow)));
                $dst = imagecreatetruecolor($tw, $th);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $ow, $oh);

                ob_start();
                imagewebp($dst, null, self::QUALITY);
                $data = ob_get_clean();
                imagedestroy($dst);

                $disk->put($variant, $data);
                unset($data);
            }

            if ($disk->exists($variant)) {
                $created[] = $variant;
            }

            // Once we've reached/passed the original width, larger sizes are pointless.
            if ($w >= $ow) {
                break;
            }
        }

        imagedestroy($src);

        return $created;
    }

    /** Variant paths that currently exist on disk (used when we skip generation). */
    private static function existing($disk, array $planned): array
    {
        return array_values(array_filter($planned, fn ($v) => $disk->exists($v)));
    }

    /**
     * Make sure there's enough memory to decode + resample an $w×$h image,
     * raising the limit when allowed. Returns false if we can't guarantee it
     * (caller should then skip generation and serve the original).
     */
    private static function ensureMemory(int $w, int $h): bool
    {
        // Source bitmap + working canvas + overhead.
        $needed = (int) ($w * $h * 4 * 3) + 64 * 1024 * 1024 + memory_get_usage(true);

        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit === -1 || $limit >= $needed) {
            return true;
        }

        @ini_set('memory_limit', (string) $needed);
        $limit = self::bytes((string) ini_get('memory_limit'));

        return $limit === -1 || $limit >= $needed;
    }

    /** Parse a php.ini shorthand byte value ("128M", "1G", "-1"). */
    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit   = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => (int) $value,
        };
    }
}
