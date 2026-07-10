<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use League\CommonMark\CommonMarkConverter;

/**
 * Server-side rendering of a post body, reproducing the behaviour the React
 * client used to perform with react-markdown:
 *  - the body is split on blank lines into paragraphs
 *  - paragraphs are divided into three roughly-equal sections
 *  - inline images (image_1/2/3 with their fits) are injected between sections
 *  - <hr> becomes a gradient divider and <blockquote> gets the indigo styling
 *
 * The DOM shape (`.post-body > div:first-child .prose p:first-child`) is kept
 * so the existing drop-cap CSS in resources/css/app.css still applies.
 */
class ArticleBody
{
    /**
     * @param  array{0:?string,1:?string,2:?string}  $images
     * @param  array{0:?string,1:?string,2:?string}  $fits
     * @return array<int, array{html:string, image:?string, fit:string}>
     */
    public static function sections(?string $body, array $images = [], array $fits = []): array
    {
        // Markdown rendering is pure: the same body/images/fits always produce the
        // same HTML. Cache on a hash of the inputs so it re-renders only when the
        // content changes (the new hash is a fresh key; stale entries expire).
        // Integer TTL (30 days) keeps this free of the date/cache facades; if the
        // cache layer is unavailable (e.g. a pure unit test) we just render.
        $key = 'article-body.'.md5(serialize([$body, $images, $fits]));

        try {
            return Cache::remember($key, 60 * 60 * 24 * 30, fn () => self::render($body, $images, $fits));
        } catch (\Throwable $e) {
            return self::render($body, $images, $fits);
        }
    }

    /**
     * @param  array{0:?string,1:?string,2:?string}  $images
     * @param  array{0:?string,1:?string,2:?string}  $fits
     * @return array<int, array{html:string, image:?string, fit:string}>
     */
    private static function render(?string $body, array $images, array $fits): array
    {
        $paragraphs = $body !== null && $body !== ''
            ? preg_split('/\n\n+/', $body)
            : [];
        $total = count($paragraphs);

        $cut1 = max(1, intdiv($total, 3));
        $cut2 = max($cut1 + 1, intdiv(2 * $total, 3));

        $chunks = [
            implode("\n\n", array_slice($paragraphs, 0, $cut1)),
            implode("\n\n", array_slice($paragraphs, $cut1, $cut2 - $cut1)),
            implode("\n\n", array_slice($paragraphs, $cut2)),
        ];

        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $sections = [];
        foreach ($chunks as $i => $text) {
            $html = trim($text) !== ''
                ? self::style((string) $converter->convert($text))
                : '';

            $sections[] = [
                'html' => $html,
                'image' => $images[$i] ?? null,
                'fit' => $fits[$i] ?? 'cover',
            ];
        }

        return $sections;
    }

    /**
     * Apply the custom renderers react-markdown used for <hr> and <blockquote>.
     */
    private static function style(string $html): string
    {
        // <hr> → gradient divider
        $html = preg_replace(
            '/<hr\s*\/?>/i',
            '<div class="my-10 h-0.5 rounded-full bg-gradient-to-r from-indigo-500 via-purple-400 to-indigo-200 opacity-60"></div>',
            $html
        );

        // <blockquote> → indigo callout (not-prose so prose styles don't fight it)
        $html = preg_replace(
            '/<blockquote>/i',
            '<blockquote class="not-prose my-6 border-l-4 border-indigo-400 bg-indigo-50/70 px-5 py-4 rounded-r-xl italic text-gray-700 leading-relaxed text-base">',
            $html
        );

        return $html;
    }
}
