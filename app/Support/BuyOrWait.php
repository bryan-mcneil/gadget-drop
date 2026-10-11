<?php

namespace App\Support;

use App\Models\ReleaseCycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The buy-or-wait verdict engine: editorial release-cycle position crossed with
 * our own tracked price position.
 *
 * DECISION MATRIX (the code below must keep matching this table):
 *
 *   cycle position        price tier            verdict            why
 *   -------------------   -------------------   ----------------   ---------------------------------------------
 *   >= 0.8 (late)         lowest                either             clearance math, a record low can beat waiting
 *   >= 0.8 (late)         anything else / none  wait_for_refresh   a successor resets prices anyway
 *   <  0.35 (fresh)       lowest | good         buy                right product, right price
 *   <  0.35 (fresh)       elevated              wait_for_price     right product, wrong number
 *   <  0.35 (fresh)       typical | none        either             nothing pushing either way
 *   0.35 - 0.8 (mid)      anything              either             honest "no strong signal"
 *
 * CONFIDENCE never reads "high" on stale cycle data (a row nobody has
 * re-checked in {@see ReleaseCycle::STALE_AFTER_MONTHS} months) or without
 * tracked price stats, the two inputs the verdict is actually made of.
 *
 * Written in the ArticleBody / PriceIntel style: no hard facade dependency on
 * the pure path (the cache call is guarded), so the matrix is unit-testable
 * without booting the framework.
 *
 * @see docs/plans/06-buy-or-wait.md §Phase 6.2
 */
class BuyOrWait
{
    public const BUY = 'buy';

    public const WAIT_FOR_REFRESH = 'wait_for_refresh';

    public const WAIT_FOR_PRICE = 'wait_for_price';

    public const EITHER = 'either';

    /**
     * Bump when the matrix, the sentence copy, or the factor shape changes.
     * The cache key is otherwise built only from DATA, so a pure code change
     * would keep serving the old wording for up to six hours after a deploy.
     * This is the deterministic bust path; `cache:clear` is the blunt one.
     */
    private const CACHE_VERSION = 'v2';

    /**
     * The verdict block for one line. `$priceStats` is a {@see PriceIntel::stats()}
     * array for the line's flagship tracked product, or null when no product we
     * review maps to the line: a cycle-only verdict, at reduced confidence.
     *
     * @param  array<string, mixed>|null  $priceStats
     * @return array{verdict: string, confidence: string, cycle_position: float, sentence: string, factors: array<int, array<string, mixed>>}
     */
    public static function verdict(ReleaseCycle $cycle, ?array $priceStats = null): array
    {
        // Self-busting key: the cycle row, the calendar day (the position moves
        // daily), and the price inputs the matrix actually reads. No flush() to
        // forget: a changed input is simply a different key.
        $key = sprintf(
            'buywait.%s.%s.%s.%s.%s.%s',
            self::CACHE_VERSION,
            $cycle->slug ?: 'unsaved',
            $cycle->updated_at?->timestamp ?? 0,
            now()->toDateString(),
            $priceStats['verdict'] ?? 'none',
            $priceStats['current'] ?? 'none',
        );

        try {
            return Cache::remember($key, now()->addHours(6), fn () => self::compute($cycle, $priceStats));
        } catch (\Throwable) {
            // Cache layer unavailable (pure unit context), so compute directly.
            return self::compute($cycle, $priceStats);
        }
    }

    /**
     * @param  array<string, mixed>|null  $priceStats
     * @return array{verdict: string, confidence: string, cycle_position: float, sentence: string, factors: array<int, array<string, mixed>>}
     */
    private static function compute(ReleaseCycle $cycle, ?array $priceStats): array
    {
        $position = $cycle->cyclePosition();
        $tier = $priceStats['verdict'] ?? null;          // lowest|good|typical|elevated|null
        $hasStats = (bool) ($priceStats['has_stats'] ?? false);
        $fresh = ! $cycle->isStale();

        $verdict = match (true) {
            $position >= ReleaseCycle::LATE_CYCLE && $tier === 'lowest' => self::EITHER,
            $position >= ReleaseCycle::LATE_CYCLE => self::WAIT_FOR_REFRESH,
            $position < ReleaseCycle::FRESH_CYCLE && in_array($tier, ['lowest', 'good'], true) => self::BUY,
            $position < ReleaseCycle::FRESH_CYCLE && $tier === 'elevated' => self::WAIT_FOR_PRICE,
            default => self::EITHER,
        };

        $confidence = $verdict === self::EITHER
            ? (($hasStats && $fresh) ? 'medium' : 'low')
            : match (true) {
                $hasStats && $fresh => 'high',
                $hasStats || $fresh => 'medium',
                default => 'low',
            };

        return [
            'verdict' => $verdict,
            'confidence' => $confidence,
            'cycle_position' => $position,
            'sentence' => self::sentence($cycle, $verdict, $position, $priceStats),
            'factors' => self::factors($cycle, $priceStats),
        ];
    }

    /**
     * The verdict as a dated, sourced, hedged human sentence. The hedging is a
     * product feature, not padding: we are describing a pattern in shipped
     * history, never making a promise about an unannounced product.
     *
     * @param  array<string, mixed>|null  $priceStats
     */
    private static function sentence(ReleaseCycle $cycle, string $verdict, float $position, ?array $priceStats): string
    {
        $line = $cycle->name;
        $months = (int) round($cycle->monthsSinceRelease());
        $released = $cycle->last_release_at?->format('F Y') ?? 'an unrecorded date';
        $cadence = $cycle->cadence_months;
        $current = $cycle->last_release_name;

        // Every sentence opens on the same sourced, dated foundation.
        $history = sprintf(
            'Historically the %s line refreshes about every %d months, and the current %s has been out %s (%s).',
            $line,
            $cadence,
            $current,
            $months === 1 ? '1 month' : "{$months} months",
            $released,
        );

        $price = self::priceClause($priceStats);

        // No em dashes anywhere in this builder: the copy it produces renders as
        // editorial prose on the page AND is quoted verbatim by AI agents, so it
        // follows the same style bans as the pipeline (CONTENT-GUIDELINES.md).
        $advice = match ($verdict) {
            self::WAIT_FOR_REFRESH => $position > 1.0
                ? sprintf(
                    'That puts it roughly %d months past the usual refresh window, so a successor is overdue. Waiting typically costs you little, and a launch usually drags this model\'s price down with it.',
                    max(1, (int) round($cycle->monthsSinceRelease() - $cadence)),
                )
                : sprintf(
                    'That is about %d%% of the way through the typical cycle, so if you can hold out for the next one, waiting is usually the better move: a launch tends to pull the outgoing model\'s price down too.',
                    (int) round($position * 100),
                ),
            self::BUY => 'That is early in the cycle, and our tracked price is currently on the good side of its own history, so by both measures this is about as well as the timing usually lines up.',
            self::WAIT_FOR_PRICE => 'The product is early in its cycle, so there is no refresh to wait for, but the price is running above its own tracked history. Waiting for the number rather than the model is typically the better trade.',
            // The "no strong signal" wording has to match where the line actually
            // sits: calling a 10-months-into-36 line "mid-cycle" was simply false.
            default => match (true) {
                $position >= ReleaseCycle::LATE_CYCLE => 'A refresh is close, but the price has fallen far enough that the clearance math can beat waiting, so either choice is defensible right now.',
                $position < ReleaseCycle::FRESH_CYCLE => 'That is early in the cycle, so there is no refresh worth holding out for, and nothing in the price history is pushing either way. No strong signal here: buy it if you need it.',
                default => 'That is mid-cycle, with nothing in the price history pushing hard either way, so there is no strong signal here: buy it if you need it, wait if you do not.',
            },
        };

        $caveat = $cycle->isStale()
            ? sprintf(' We last re-checked this line\'s dates in %s, so treat the cycle half as directional.', $cycle->verified_at?->format('F Y') ?? 'an unrecorded month')
            : '';

        return trim("{$history} {$price}{$advice}{$caveat}");
    }

    /**
     * The price half of the sentence: always dated, always explicit that the
     * numbers are ours rather than a live retailer price.
     *
     * @param  array<string, mixed>|null  $priceStats
     */
    private static function priceClause(?array $priceStats): string
    {
        if (empty($priceStats['has_stats'])) {
            return 'We do not yet have enough tracked price history on this line to weigh the price in, so this read is based on release timing alone. ';
        }

        $current = '$'.number_format((float) $priceStats['current'], 2);
        $avg = '$'.number_format((float) $priceStats['avg90'], 2);
        $checked = $priceStats['checked_at']
            ? Carbon::parse($priceStats['checked_at'])->format('M j, Y')
            : null;

        $tier = match ($priceStats['verdict']) {
            'lowest' => "the lowest we have tracked in 90 days (typically {$avg})",
            'good' => "below its tracked 90-day average of {$avg}",
            'elevated' => "above its tracked 90-day average of {$avg}",
            default => "in line with its tracked 90-day average of {$avg}",
        };

        return sprintf(
            'On price, the model we track is %s, %s%s. ',
            $current,
            $tier,
            $checked ? " as of {$checked}" : '',
        );
    }

    /**
     * Every input to the verdict, each with where it came from and when it was
     * last checked. The page renders this list verbatim, so a reader can audit the
     * verdict without trusting it.
     *
     * @param  array<string, mixed>|null  $priceStats
     * @return array<int, array<string, mixed>>
     */
    private static function factors(ReleaseCycle $cycle, ?array $priceStats): array
    {
        $factors = [];

        $factors[] = [
            'label' => 'Release cadence',
            'detail' => trim(sprintf(
                '%s has refreshed about every %d months%s. %s',
                $cycle->name,
                $cycle->cadence_months,
                $cycle->typical_month ? ', usually around '.Carbon::create(null, $cycle->typical_month, 1)->format('F') : '',
                $cycle->next_expected_note ?? '',
            )),
            'source_url' => $cycle->source_url,
            'as_of' => $cycle->verified_at?->toDateString(),
        ];

        $factors[] = [
            'label' => 'Current model',
            'detail' => sprintf(
                '%s has been on sale since %s: %d months, against a %d-month cycle.',
                $cycle->last_release_name,
                $cycle->last_release_at?->format('F j, Y') ?? 'an unrecorded date',
                (int) round($cycle->monthsSinceRelease()),
                $cycle->cadence_months,
            ),
            'source_url' => $cycle->source_url,
            'as_of' => $cycle->verified_at?->toDateString(),
        ];

        if (! empty($priceStats['has_stats'])) {
            $factors[] = [
                'label' => 'Tracked price',
                'detail' => sprintf(
                    'Currently $%s against a tracked 90-day average of $%s (low $%s, high $%s). These are our own recorded prices, not a list price.',
                    number_format((float) $priceStats['current'], 2),
                    number_format((float) $priceStats['avg90'], 2),
                    number_format((float) $priceStats['low90'], 2),
                    number_format((float) $priceStats['high90'], 2),
                ),
                'source_url' => null,
                'as_of' => $priceStats['checked_at']
                    ? Carbon::parse($priceStats['checked_at'])->toDateString()
                    : null,
            ];
        } else {
            $factors[] = [
                'label' => 'Tracked price',
                'detail' => sprintf(
                    'Not weighed in: no product on this line has cleared our price-history gate yet (at least %d snapshots spanning %d days).',
                    PriceIntel::MIN_POINTS,
                    PriceIntel::MIN_SPAN_DAYS,
                ),
                'source_url' => null,
                'as_of' => null,
            ];
        }

        return $factors;
    }

    /**
     * The cache-key prefix callers should embed in their own derived caches
     * (the /buy-or-wait index row cache), so one version bump busts them all.
     */
    public static function cacheNamespace(): string
    {
        return 'buywait.'.self::CACHE_VERSION;
    }

    /** Headline label for the verdict hero and the index chips. */
    public static function label(string $verdict): string
    {
        return match ($verdict) {
            self::BUY => 'Buy now',
            self::WAIT_FOR_REFRESH => 'Wait for the refresh',
            self::WAIT_FOR_PRICE => 'Wait for a better price',
            default => 'No strong signal',
        };
    }

    /** Compact label for the review-page strip, where the line name carries the context. */
    public static function shortLabel(string $verdict): string
    {
        return match ($verdict) {
            self::BUY => 'good time to buy',
            self::WAIT_FOR_REFRESH => 'a refresh is close',
            self::WAIT_FOR_PRICE => 'wait for a better price',
            default => 'no strong signal',
        };
    }

    /** Tailwind colour key per verdict, kept here so every surface agrees. */
    public static function tone(string $verdict): string
    {
        return match ($verdict) {
            self::BUY => 'emerald',
            self::WAIT_FOR_REFRESH => 'amber',
            self::WAIT_FOR_PRICE => 'sky',
            default => 'gray',
        };
    }
}
