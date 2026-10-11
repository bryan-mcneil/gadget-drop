<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Render-side helper for responsive images. Given a stored image URL it builds
 * a WebP `srcset` from any variants {@see ImageVariants} produced, so the
 * <x-responsive-image> component can serve smaller WebP files while keeping the
 * original as the <img src> fallback.
 *
 * External URLs (e.g. Amazon-hosted images) and images without generated
 * variants return an empty srcset, so the component degrades to a plain <img>.
 */
class ResponsiveImage
{
    /**
     * Map a stored image URL to its path on the `public` disk, or null when the
     * URL is not a local /storage/ image (external host, empty, etc.).
     */
    public static function relativePath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $needle = '/storage/';
        $pos = strpos($url, $needle);
        if ($pos === false) {
            return null;
        }

        $rel = substr($url, $pos + strlen($needle));

        // Strip any query string / fragment.
        return preg_replace('/[?#].*$/', '', $rel) ?: null;
    }

    /**
     * URL of ONE WebP variant for contexts that take a single image URL and no
     * srcset (the recap video's `poster`, VideoObject thumbnailUrl). Picks the
     * smallest variant at least $width wide (falling back to the largest one
     * below it), or null when no local variants exist — callers then use the
     * original image URL.
     */
    public static function webpVariantUrl(?string $url, int $width = 960): ?string
    {
        $rel = self::relativePath($url);
        if ($rel === null) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($rel)) {
            return null;
        }

        $info = pathinfo($rel);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';
        $name = $info['filename'];

        $best = null;
        foreach (ImageVariants::WIDTHS as $w) {
            $variant = "{$dir}{$name}-{$w}.webp";
            if (! $disk->exists($variant)) {
                continue;
            }
            $best = $disk->url($variant);
            if ($w >= $width) {
                break; // WIDTHS is ascending — first one wide enough wins
            }
        }

        return $best;
    }

    /**
     * Build a WebP srcset string for a stored image URL, or '' when no local
     * WebP variants exist (so the component skips the <source> entirely).
     */
    public static function webpSrcset(?string $url): string
    {
        $rel = self::relativePath($url);
        if ($rel === null) {
            return '';
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($rel)) {
            return '';
        }

        $info = pathinfo($rel);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';
        $name = $info['filename'];

        $parts = [];
        foreach (ImageVariants::WIDTHS as $w) {
            $variant = "{$dir}{$name}-{$w}.webp";
            if ($disk->exists($variant)) {
                $parts[] = $disk->url($variant)." {$w}w";
            }
        }

        return implode(', ', $parts);
    }
}
