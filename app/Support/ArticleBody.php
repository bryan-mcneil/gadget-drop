<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

/**
 * Server-side rendering of a post body with structure-aware inline images:
 *  - the body is split on blank lines into blocks, each classified as
 *    paragraph / heading / hr / list / blockquote
 *  - inline images (image_1/2/3 with fit + caption) are placed only in legal
 *    slots: between two paragraphs of running text (first tier), or after the
 *    closing paragraph of a section that continues (second tier) — never
 *    directly against a heading or divider, and never at the document end
 *    while an in-flow slot exists
 *  - slots are spread across the reading flow by cumulative text length
 *  - <hr> becomes a gradient divider and <blockquote> gets the indigo styling
 *
 * The DOM shape (`.post-body > div:first-child .prose p:first-child`) is kept
 * so the existing drop-cap CSS in resources/css/app.css still applies: the
 * first section always starts at the first block of the body.
 */
class ArticleBody
{
    /**
     * Bumped whenever placement or markup logic changes so every cached render
     * dies with its old key on deploy (keys are input-hashed, entries expire).
     */
    private const VERSION = 'v3';

    /**
     * @param  array{0:?string,1:?string,2:?string}  $images
     * @param  array{0:?string,1:?string,2:?string}  $fits
     * @param  array{0:?string,1:?string,2:?string}  $captions
     * @return array<int, array{html:string, image:?string, fit:string, caption:?string}>
     */
    public static function sections(?string $body, array $images = [], array $fits = [], array $captions = []): array
    {
        // Markdown rendering is pure: the same inputs always produce the same
        // HTML. Cache on a hash of the inputs so it re-renders only when the
        // content changes. Integer TTL (30 days) keeps this free of the
        // date/cache facades; if the cache layer is unavailable (e.g. a pure
        // unit test) we just render.
        $key = 'article-body.'.self::VERSION.'.'.md5(serialize([$body, $images, $fits, $captions]));

        try {
            return Cache::remember($key, 60 * 60 * 24 * 30, fn () => self::render($body, $images, $fits, $captions));
        } catch (\Throwable $e) {
            return self::render($body, $images, $fits, $captions);
        }
    }

    /**
     * The H2 section headings of a body, in document order, for the post page's
     * "On this page" anchor list. Each heading is rendered through the same
     * CommonMark converter the body uses, then reduced to its plain text — the
     * identical path style() takes over the <h2> it stamps — so headingSlug()
     * produces the SAME slug on both sides even when a heading carries inline
     * markdown (a link, code, emphasis). The anchor always finds its heading.
     *
     * H2 only (##, not # or ###): H1 is the page title (never in the body) and
     * H3s are sub-points, so a flat one-level table of contents stays scannable.
     * CommonMark is a plain object (no facade), so ArticleBodyTest can exercise
     * this without booting the framework.
     *
     * @return array<int, array{text: string, slug: string}>
     */
    public static function headings(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return [];
        }

        $blocks = array_values(array_filter(array_map('trim', preg_split('/\n\n+/', $body)), fn ($b) => $b !== ''));

        $converter = null;
        $out = [];
        foreach ($blocks as $block) {
            $firstLine = strtok($block, "\n") ?: $block;
            if (! preg_match('/^##\s+(.+)$/', $firstLine, $m)) {
                continue;
            }
            $converter ??= self::converter();
            $html = (string) $converter->convert('## '.$m[1]);
            if (! preg_match('/<h2>(.*?)<\/h2>/is', $html, $h)) {
                continue;
            }
            $text = trim(strip_tags($h[1]));
            $slug = self::headingSlug($h[1]);
            if ($slug !== '') {
                $out[] = ['text' => $text, 'slug' => $slug];
            }
        }

        return $out;
    }

    /** The scroll-anchor slug for a rendered <h2>'s inner HTML. Single source
     *  of the id, shared by headings() and style() so they never disagree. */
    private static function headingSlug(string $innerHtml): string
    {
        return Str::slug(strip_tags($innerHtml));
    }

    private static function converter(): CommonMarkConverter
    {
        return new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return array<int, array{html:string, image:?string, fit:string, caption:?string}>
     */
    private static function render(?string $body, array $images, array $fits, array $captions): array
    {
        $blocks = $body !== null && trim($body) !== ''
            ? array_values(array_filter(array_map('trim', preg_split('/\n\n+/', $body)), fn ($b) => $b !== ''))
            : [];

        $media = [];
        for ($i = 0; $i < 3; $i++) {
            if (! empty($images[$i])) {
                $fit = in_array($fits[$i] ?? null, ['cover', 'contain'], true) ? $fits[$i] : 'cover';
                $caption = trim((string) ($captions[$i] ?? ''));
                $media[] = ['image' => $images[$i], 'fit' => $fit, 'caption' => $caption !== '' ? $caption : null];
            }
        }

        if ($blocks === []) {
            // No body: still render any uploaded images so content is never lost.
            return array_map(
                fn ($m) => ['html' => '', 'image' => $m['image'], 'fit' => $m['fit'], 'caption' => $m['caption']],
                $media
            );
        }

        $types = array_map(self::classify(...), $blocks);
        $slots = self::chooseSlots($types, $blocks, count($media));

        $converter = self::converter();

        $sections = [];
        $start = 0;
        foreach ($media as $k => $m) {
            $end = $slots[$k];
            $text = $end >= $start ? implode("\n\n", array_slice($blocks, $start, $end - $start + 1)) : '';
            $sections[] = [
                'html' => $text !== '' ? self::style((string) $converter->convert($text)) : '',
                'image' => $m['image'],
                'fit' => $m['fit'],
                'caption' => $m['caption'],
            ];
            $start = max($start, $end + 1);
        }

        $tail = $start < count($blocks) ? implode("\n\n", array_slice($blocks, $start)) : '';
        if ($tail !== '' || $sections === []) {
            $sections[] = [
                'html' => $tail !== '' ? self::style((string) $converter->convert($tail)) : '',
                'image' => null,
                'fit' => 'cover',
                'caption' => null,
            ];
        }

        return $sections;
    }

    /**
     * One markdown block → its structural role. Blocks are blank-line
     * separated, so a heading and its paragraph normally arrive separately.
     */
    private static function classify(string $block): string
    {
        if (preg_match('/^#{1,6}\s/', $block)) {
            return 'heading';
        }
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', trim($block))) {
            return 'hr';
        }
        if (str_starts_with($block, '>')) {
            return 'quote';
        }
        $firstLine = strtok($block, "\n") ?: $block;
        if (preg_match('/^([-*+]|\d+\.)\s/', $firstLine)) {
            return 'list';
        }

        return 'paragraph';
    }

    /**
     * Pick one insertion index per image (image renders AFTER that block).
     *
     * Tier 1: between two paragraphs of running text.
     * Tier 2: after a paragraph that closes a section which continues (next
     *         block is a heading, or a divider with content beyond it).
     * Fallback: after the last paragraph — only when no legal slot remains,
     *         because dropping an uploaded image is worse than a tail image.
     *
     * Targets sit at fractions of cumulative text length so images spread
     * through the *reading flow* rather than the block count.
     *
     * @param  array<int, string>  $types
     * @param  array<int, string>  $blocks
     * @return array<int, int> ascending block indices, one per image
     */
    private static function chooseSlots(array $types, array $blocks, int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $n = count($blocks);
        $tier1 = [];
        $tier2 = [];
        for ($i = 0; $i < $n - 1; $i++) {
            if ($types[$i] !== 'paragraph') {
                continue;
            }
            if ($types[$i + 1] === 'paragraph') {
                $tier1[] = $i;
            } elseif ($types[$i + 1] === 'heading' || ($types[$i + 1] === 'hr' && $i + 2 < $n)) {
                $tier2[] = $i;
            }
        }

        $lengths = array_map(mb_strlen(...), $blocks);
        $total = max(1, array_sum($lengths));
        $cum = [];
        $running = 0;
        foreach ($lengths as $i => $len) {
            $running += $len;
            $cum[$i] = $running / $total;
        }

        $targets = [1 => [0.5], 2 => [0.35, 0.7], 3 => [0.3, 0.55, 0.8]][min(3, $count)];

        $chosen = [];
        foreach ($targets as $target) {
            $slot = self::nearestSlot($tier1, $cum, $target, $chosen, 2)
                ?? self::nearestSlot($tier2, $cum, $target, $chosen, 2)
                ?? self::nearestSlot($tier1, $cum, $target, $chosen, 1)
                ?? self::nearestSlot($tier2, $cum, $target, $chosen, 1);
            if ($slot !== null) {
                $chosen[] = $slot;
            }
        }

        if (count($chosen) < $count) {
            $lastParagraph = null;
            for ($i = $n - 1; $i >= 0; $i--) {
                if ($types[$i] === 'paragraph') {
                    $lastParagraph = $i;
                    break;
                }
            }
            $fallback = $lastParagraph ?? $n - 1;
            while (count($chosen) < $count) {
                $chosen[] = $fallback;
            }
        }

        sort($chosen);

        return $chosen;
    }

    /**
     * @param  array<int, int>  $pool
     * @param  array<int, float>  $cum
     * @param  array<int, int>  $chosen
     */
    private static function nearestSlot(array $pool, array $cum, float $target, array $chosen, int $minGap): ?int
    {
        $best = null;
        $bestDistance = INF;
        foreach ($pool as $i) {
            foreach ($chosen as $c) {
                if (abs($i - $c) < $minGap) {
                    continue 2;
                }
            }
            $distance = abs($cum[$i] - $target);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $i;
            }
        }

        return $best;
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

        // <h2> → scroll anchor, id = headingSlug() (shared with headings() so the
        // "On this page" links always land). Only stamps bare <h2> (CommonMark
        // emits attribute-free headings), so a re-run never double-stamps.
        $html = preg_replace_callback(
            '/<h2>(.*?)<\/h2>/is',
            function (array $m): string {
                $slug = self::headingSlug($m[1]);

                return $slug !== '' ? '<h2 id="'.$slug.'">'.$m[1].'</h2>' : $m[0];
            },
            $html
        );

        return $html;
    }
}
