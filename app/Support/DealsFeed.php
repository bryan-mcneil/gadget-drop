<?php

namespace App\Support;

use App\Models\Product;
use App\Models\WorthItVote;
use Illuminate\Support\Facades\Cache;

/**
 * The qualifying-deals feed — products whose CURRENT price sits meaningfully
 * below what our own snapshot history says is typical. One source of truth for
 * every surface that renders it: the /deals page (DealsController) and the MCP
 * list_tracked_deals tool. Cached 1h under 'deals.feed'; PriceIntel::flush()
 * busts it whenever any product's stats change.
 */
class DealsFeed
{
    /** Minimum percent below the 90-day average to qualify as a drop. */
    public const MIN_DROP_PCT = 5;

    /** Max entries in the feed. */
    public const MAX_ENTRIES = 24;

    /**
     * Same defensive cache pattern as PriceIntel::stats() — a broken cache
     * layer degrades to a live compute, never an empty feed.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get(): array
    {
        try {
            return Cache::remember('deals.feed', now()->addHour(), fn () => self::build());
        } catch (\Throwable) {
            return self::build();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function build(): array
    {
        // Product counts are small (low hundreds); assembling in PHP keeps the
        // qualifying rules in one place (PriceIntel) and works on any driver.
        $candidates = Product::query()
            ->whereNotNull('price')
            ->whereHas('posts', fn ($q) => $q->published())
            ->whereHas('priceSnapshots')
            ->with(['posts' => fn ($q) => $q->published()
                ->whereNotIn('type', ['tech_tip', 'tech_news'])
                ->latest('published_at')
                ->select(['posts.id', 'title', 'slug', 'published_at'])
                // Worth-it tallies folded into the eager-load (no per-post N+1).
                ->withCount([
                    'worthItVotes as worth_count' => fn ($q) => $q->where('choice', 'worth'),
                    'worthItVotes as skip_count' => fn ($q) => $q->where('choice', 'skip'),
                ]),
            ])
            ->get();

        $deals = [];

        foreach ($candidates as $product) {
            $stats = PriceIntel::stats($product->id);

            if (
                ! $stats
                || ! $stats['has_stats']
                || ! in_array($stats['verdict'], ['lowest', 'good'], true)
                || ($stats['drop_pct'] ?? 0) < self::MIN_DROP_PCT
            ) {
                continue;
            }

            $post = $product->posts->first();

            if (! $post) {
                continue;
            }

            // Read-only worth-it social proof (same ≥5-vote honesty gate as the
            // post page); pct stays null below the gate and the card hides the line.
            $worth = WorthItVote::summarize((int) ($post->worth_count ?? 0), (int) ($post->skip_count ?? 0));

            $deals[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'current' => $stats['current'],
                'typical' => $stats['avg90'],
                'low90' => $stats['low90'],
                'low30' => $stats['low30'],
                'drop_pct' => $stats['drop_pct'],
                'verdict' => $stats['verdict'],
                'checked_at' => $stats['checked_at'],
                'points' => $stats['points'],
                'post_id' => $post->id,
                'post_title' => $post->title,
                'post_slug' => $post->slug,
                'worth_pct' => $worth['pct'],
                'worth_total' => $worth['total'],
            ];
        }

        usort($deals, fn ($a, $b) => $b['drop_pct'] <=> $a['drop_pct']);

        return array_slice($deals, 0, self::MAX_ENTRIES);
    }
}
