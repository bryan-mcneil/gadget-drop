<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WorthItVote;
use App\Support\PriceIntel;
use App\Support\TruthReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * "Price Drops" feed — products whose CURRENT price sits meaningfully below
 * what our own snapshot history says is typical. Every number on the page
 * comes from tracked data (never an MSRP), and products qualify only once
 * PriceIntel's honesty gates pass, so an empty feed is a truthful feed.
 */
class DealsController extends Controller
{
    /** Minimum percent below the 90-day average to qualify as a drop. */
    public const MIN_DROP_PCT = 5;

    /** Max entries on the page. */
    public const MAX_ENTRIES = 24;

    public function index(): View
    {
        try {
            $deals = Cache::remember('deals.feed', now()->addHour(), fn () => $this->buildFeed());
        } catch (\Throwable) {
            $deals = $this->buildFeed();
        }

        view()->share('serverMeta', [
            'title' => 'Tech Price Drops We Actually Tracked | GadgetDrop',
            'description' => 'Real price drops on gadgets we cover — measured against our own recorded price history, not inflated list prices. Updated as our tracker sees changes.',
            'og_image' => null,
            'og_type' => 'website',
            'canonical' => route('deals'),
        ]);

        // Event-week pointer to a Truth Report: config names the slug AND the
        // report must pass the publication gate, or the line stays hidden.
        $promoSlug = config('truth.promote_on_deals');
        $published = TruthReport::published();

        return view('public.deals', [
            'deals' => $deals,
            'truthPromo' => ($promoSlug && isset($published[$promoSlug]))
                ? ['slug' => $promoSlug, 'title' => $published[$promoSlug]['title']]
                : null,
        ]);
    }

    private function buildFeed(): array
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
