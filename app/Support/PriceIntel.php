<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Price-history math for the tracked-price widget and the /deals feed.
 *
 * Honesty rules, enforced here so no template can fudge them:
 *  - `current` + `checked_at` are always available for a priced product; the
 *    "Price checked {date}" label is the minimum truth every page shows.
 *  - low/avg/high and the verdict are NULL until the product has at least
 *    MIN_POINTS snapshots spanning MIN_SPAN_DAYS. The span is what carries
 *    the honesty: two points collected yesterday say nothing, but "held at
 *    $179 for three weeks, now $149" is a true statement from two points —
 *    the carry-forward series weighs a price by how long it held.
 *  - Window stats weigh a price by how long it held (carry-forward daily
 *    series), not by how often we happened to check.
 */
class PriceIntel
{
    /** Snapshots required before window stats / verdicts are shown. */
    public const MIN_POINTS = 2;

    /** Days of history required before window stats / verdicts are shown. */
    public const MIN_SPAN_DAYS = 14;

    /** A current price ≥ this fraction below the 90-day average is a "good" deal. */
    public const DEAL_PCT = 0.05;

    /** Max points emitted for the sparkline. */
    public const MAX_SPARK_POINTS = 30;

    /**
     * Cached stats for one product, or null when the product has no price.
     * Same defensive cache pattern as DropPrice::today() — a broken cache
     * layer degrades to a live compute, never a blank widget.
     */
    public static function stats(int $productId): ?array
    {
        try {
            return Cache::remember("priceintel.{$productId}", now()->addHours(6), fn () => self::compute($productId));
        } catch (\Throwable) {
            return self::compute($productId);
        }
    }

    /** Forget one product's stats and the /deals feed that aggregates them. */
    public static function flush(int $productId): void
    {
        try {
            Cache::forget("priceintel.{$productId}");
            Cache::forget('deals.feed');
        } catch (\Throwable) {
            // Cache not bound (pure unit context) — nothing to flush.
        }
    }

    private static function compute(int $productId): ?array
    {
        $product = Product::find($productId);

        if (! $product || $product->price === null) {
            return null;
        }

        $current = (float) $product->price;

        $snapshots = $product->priceSnapshots()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['price', 'created_at'])
            ->map(fn ($s) => ['date' => $s->created_at->copy()->startOfDay(), 'price' => (float) $s->price])
            ->values();

        $result = [
            'current' => $current,
            'checked_at' => $product->price_checked_at?->toIso8601String(),
            'tracking_since' => $snapshots->isNotEmpty() ? $snapshots->first()['date']->toIso8601String() : null,
            'has_stats' => false,
            'low30' => null,
            'high30' => null,
            'avg30' => null,
            'low90' => null,
            'high90' => null,
            'avg90' => null,
            'verdict' => null,
            'drop_pct' => null,
            'points' => [],
        ];

        $spanDays = $snapshots->isEmpty()
            ? 0
            : $snapshots->first()['date']->diffInDays(now());

        if ($snapshots->count() < self::MIN_POINTS || $spanDays < self::MIN_SPAN_DAYS) {
            return $result;
        }

        // Daily carry-forward series across the last 90 days (a price that held
        // for 60 days counts 60 times). Seed with the price in effect at the
        // window start, then walk day by day.
        $daily = self::dailySeries($snapshots, 90);

        $window = fn (int $days) => array_slice($daily, -$days, $days);

        $w30 = array_column($window(30), 'price');
        $w90 = array_column($window(90), 'price');

        $result['has_stats'] = true;
        $result['low30'] = round(min($w30), 2);
        $result['high30'] = round(max($w30), 2);
        $result['avg30'] = round(array_sum($w30) / count($w30), 2);
        $result['low90'] = round(min($w90), 2);
        $result['high90'] = round(max($w90), 2);
        $result['avg90'] = round(array_sum($w90) / count($w90), 2);

        // "Lowest tracked" needs actual variation in the window — a price that
        // never moved is typical, not a record low.
        $hasVariation = ($result['high90'] - $result['low90']) > 0.009;

        $result['verdict'] = match (true) {
            $hasVariation && $current <= $result['low90'] + 0.009 => 'lowest',
            $current <= $result['avg90'] * (1 - self::DEAL_PCT) => 'good',
            $current >= $result['avg90'] * (1 + self::DEAL_PCT) => 'elevated',
            default => 'typical',
        };

        if ($current < $result['avg90']) {
            $result['drop_pct'] = round(($result['avg90'] - $current) / $result['avg90'] * 100, 1);
        }

        // Downsample the daily series for the sparkline.
        $step = max(1, (int) ceil(count($daily) / self::MAX_SPARK_POINTS));
        $points = [];
        foreach ($daily as $i => $day) {
            if ($i % $step === 0 || $i === count($daily) - 1) {
                $points[] = [$day['date'], $day['price']];
            }
        }
        $result['points'] = $points;

        return $result;
    }

    /**
     * @param  Collection<int, array{date: Carbon, price: float}>  $snapshots  ascending
     * @return array<int, array{date: string, price: float}> one entry per day, oldest first
     */
    private static function dailySeries($snapshots, int $days): array
    {
        $start = now()->copy()->subDays($days - 1)->startOfDay();

        // Price in effect when the window opens: last snapshot at or before start,
        // else the first snapshot's price (window opens before tracking began).
        $carry = null;
        foreach ($snapshots as $snap) {
            if ($snap['date']->lte($start)) {
                $carry = $snap['price'];
            }
        }
        $carry ??= $snapshots->first()['price'];

        $byDay = [];
        foreach ($snapshots as $snap) {
            // Same-day snapshots: the later one (collection is ascending) wins.
            $byDay[$snap['date']->toDateString()] = $snap['price'];
        }

        $series = [];
        $cursor = $start->copy();
        $today = now()->startOfDay();

        while ($cursor->lte($today)) {
            $key = $cursor->toDateString();
            if (array_key_exists($key, $byDay)) {
                $carry = $byDay[$key];
            }
            $series[] = ['date' => $key, 'price' => $carry];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * SVG polyline `points` string for a sparkline, y-inverted, with a little
     * vertical padding. Pure math so the Blade component stays dumb.
     *
     * @param  array<int, array{0: string, 1: float}>  $points  [date, price] pairs
     */
    public static function sparklinePoints(array $points, int $width = 240, int $height = 48): string
    {
        $n = count($points);

        if ($n === 0) {
            return '';
        }

        $prices = array_map(fn ($p) => $p[1], $points);
        $min = min($prices);
        $max = max($prices);
        $range = $max - $min;
        $pad = 4;

        $coords = [];
        foreach ($prices as $i => $price) {
            $x = $n > 1 ? $i / ($n - 1) * $width : $width / 2;
            $y = $range > 0
                ? $pad + (1 - ($price - $min) / $range) * ($height - 2 * $pad)
                : $height / 2;
            $coords[] = round($x, 1).','.round($y, 1);
        }

        // A single point still draws as a visible flat dash.
        if ($n === 1) {
            $only = explode(',', $coords[0]);
            $coords = [($width / 2 - 12).','.$only[1], ($width / 2 + 12).','.$only[1]];
        }

        return implode(' ', $coords);
    }
}
