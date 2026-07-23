<?php

namespace App\Http\Controllers;

use App\Mcp\Support\ProductResolver;
use App\Models\Post;
use App\Models\ReleaseCycle;
use App\Support\BuyOrWait;
use App\Support\PriceIntel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Public buy-or-wait pages: the living verdict on when to buy a product line,
 * built from editorial release-cycle data crossed with our own price history.
 *
 * Editorial-integrity surface, same rules as the Truth Report pages: NO
 * affiliate links here. The price block is informational and the CTA is an
 * internal link to the review, which holds the single affiliate link. The dated
 * title ("Should you buy the iPhone now? (July 2026)") is deliberate, because the
 * question is time-sensitive and the page answers it as of a stated date.
 *
 * @see docs/plans/06-buy-or-wait.md §Phase 6.3
 */
class BuyOrWaitController extends Controller
{
    /** Related reviews shown under a cycle's verdict. */
    private const RELATED_REVIEWS = 4;

    public function index(): View
    {
        $rows = $this->cachedIndex();

        // An index with nothing on it is a thin page; the site's rule is not to
        // publish one (same call the /truth index makes).
        abort_if($rows === [], 404);

        view()->share('serverMeta', [
            'title' => 'Buy or Wait? Tech Release Cycles and Price Timing | GadgetDrop',
            'description' => 'Should you buy now or wait for the next model? Sourced release-cycle history for the gadget lines we cover, crossed with our own tracked price data. Updated as cycles move.',
            'og_image' => null,
            'og_type' => 'website',
            'canonical' => route('buy-or-wait.index'),
        ]);

        return view('public.buy-or-wait.index', [
            'waiting' => array_values(array_filter($rows, fn ($r) => $r['verdict'] === BuyOrWait::WAIT_FOR_REFRESH)),
            'buying' => array_values(array_filter($rows, fn ($r) => $r['verdict'] === BuyOrWait::BUY)),
            'neutral' => array_values(array_filter(
                $rows,
                fn ($r) => ! in_array($r['verdict'], [BuyOrWait::WAIT_FOR_REFRESH, BuyOrWait::BUY], true),
            )),
            'total' => count($rows),
        ]);
    }

    public function show(ReleaseCycle $cycle): View
    {
        $product = $cycle->flagshipProduct();
        $stats = $product ? PriceIntel::stats($product->id) : null;
        $verdict = BuyOrWait::verdict($cycle, $stats);
        $review = $product ? ProductResolver::reviewFor($product) : null;

        $title = "Should you buy the {$cycle->name} now or wait? (".now()->format('F Y').')';

        view()->share('serverMeta', [
            'title' => "{$title} | GadgetDrop",
            'description' => $this->metaDescription($cycle, $verdict),
            'og_image' => null,
            'og_type' => 'article',
            'canonical' => route('buy-or-wait.show', $cycle->slug),
        ]);
        view()->share('serverJsonLd', $this->buildFaqJsonLd($cycle, $verdict));

        return view('public.buy-or-wait.show', [
            'cycle' => $cycle,
            'verdict' => $verdict,
            'title' => $title,
            'stats' => $stats,
            'product' => $product,
            'review' => $review,
            'relatedReviews' => $this->relatedReviews($cycle, $review?->id),
        ]);
    }

    /**
     * The index rows. Assembling them costs a flagship lookup plus a PriceIntel
     * read per line, so the finished array (plain scalars, safe on the database
     * cache store) is cached for six hours, keyed by the calendar day because a
     * cycle position moves every day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cachedIndex(): array
    {
        $build = function (): array {
            return ReleaseCycle::ordered()->map(function (ReleaseCycle $cycle) {
                $product = $cycle->flagshipProduct();
                $result = BuyOrWait::verdict($cycle, $product ? PriceIntel::stats($product->id) : null);

                return [
                    'slug' => $cycle->slug,
                    'name' => $cycle->name,
                    'last_release_name' => $cycle->last_release_name,
                    'months' => (int) round($cycle->monthsSinceRelease()),
                    'cadence_months' => $cycle->cadence_months,
                    'verified_at' => $cycle->verified_at?->toDateString(),
                    'is_stale' => $cycle->isStale(),
                    'verdict' => $result['verdict'],
                    'confidence' => $result['confidence'],
                    'cycle_position' => $result['cycle_position'],
                    'sentence' => $result['sentence'],
                ];
            })->all();
        };

        try {
            // Namespaced through BuyOrWait so a verdict-copy version bump busts
            // this derived cache too, not just the per-cycle verdicts inside it.
            $key = BuyOrWait::cacheNamespace().'.index.'.now()->toDateString();

            return Cache::remember($key, now()->addHours(6), $build);
        } catch (\Throwable) {
            return $build();
        }
    }

    /**
     * Other reviews a reader weighing this line would want. Category-scoped, so
     * a line with no category simply shows none rather than padding the page.
     *
     * @return Collection<int, Post>
     */
    private function relatedReviews(ReleaseCycle $cycle, ?int $excludeId)
    {
        if (! $cycle->category_id) {
            return collect();
        }

        return Post::published()
            ->whereNotIn('type', ['tech_tip', 'tech_news'])
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $cycle->category_id))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->latest('published_at')
            ->take(self::RELATED_REVIEWS)
            ->get(['id', 'title', 'slug', 'published_at', 'featured_image']);
    }

    /** @param array<string, mixed> $verdict */
    private function metaDescription(ReleaseCycle $cycle, array $verdict): string
    {
        return sprintf(
            '%s The %s line refreshes about every %d months; %s has been out %d. Our verdict, the sources behind it, and our own tracked price history.',
            BuyOrWait::label($verdict['verdict']).'.',
            $cycle->name,
            $cycle->cadence_months,
            $cycle->last_release_name,
            (int) round($cycle->monthsSinceRelease()),
        );
    }

    /**
     * FAQPage: the verdict really is a question-and-answer, and answer engines
     * index it far more readily than the same claim in prose.
     *
     * @param  array<string, mixed>  $verdict
     */
    private function buildFaqJsonLd(ReleaseCycle $cycle, array $verdict): string
    {
        $questions = [[
            '@type' => 'Question',
            'name' => "Should I buy the {$cycle->name} now or wait?",
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => BuyOrWait::label($verdict['verdict']).'. '.$verdict['sentence'],
            ],
        ]];

        if ($cycle->next_expected_note) {
            $questions[] = [
                '@type' => 'Question',
                'name' => "When is the next {$cycle->name} expected?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => sprintf(
                        '%s We do not publish predictions for unannounced products. This is the shipped pattern, last checked against its source on %s.',
                        $cycle->next_expected_note,
                        $cycle->verified_at?->format('F j, Y') ?? 'an unrecorded date',
                    ),
                ],
            ];
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'url' => route('buy-or-wait.show', $cycle->slug),
            'mainEntity' => $questions,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
