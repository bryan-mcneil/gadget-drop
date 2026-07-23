<?php

namespace App\Mcp\Concerns;

use App\Mcp\Support\ProductResolver;
use App\Models\Post;
use App\Models\Product;
use App\Support\PriceIntel;
use Laravel\Mcp\Response;

/**
 * The shared shape of every Price-Truth tool response: the disclosure, the
 * review/affiliate/methodology URLs, the honesty-gate reason, and the price
 * payload assembled from PriceIntel::stats(). Affiliate links ALWAYS route
 * through /out/{product} (never a raw amazon.com URL) so click attribution and
 * the auto-appended tag survive agent-mediated shopping.
 */
trait BuildsPriceTruthResponses
{
    /** Relayed verbatim by agents — keep it a complete, honest sentence. */
    protected function disclosure(): string
    {
        return 'GadgetDrop earns a commission on Amazon purchases made through the included affiliate link.';
    }

    protected function methodologyUrl(): string
    {
        return route('how-we-review').'#deal-verdicts';
    }

    /** Quoted to agents when stats are honesty-gated; sourced from the real constants so it can't drift. */
    protected function gateReason(): string
    {
        return sprintf(
            'insufficient history (need ≥%d snapshots spanning ≥%d days)',
            PriceIntel::MIN_POINTS,
            PriceIntel::MIN_SPAN_DAYS,
        );
    }

    protected function affiliateUrl(int $productId, ?int $postId = null): string
    {
        return route('affiliate.redirect', array_filter([
            'product' => $productId,
            'post' => $postId,
        ]));
    }

    /** Calm not-found error that points the agent at the discovery tool. */
    protected function notTracked(string $identifier): Response
    {
        return Response::error(sprintf(
            'No tracked product matched %s. GadgetDrop only serves price data for products with a published review — call the search_tracked_products tool to see what we cover.',
            $identifier,
        ));
    }

    /**
     * The common price payload. `checked_at` is always present (the
     * minimum-truth rule); window stats are null with an explicit gate reason
     * until the product clears PriceIntel's honesty gates.
     *
     * @param  array<string, mixed>|null  $stats  PriceIntel::stats() result
     * @return array<string, mixed>
     */
    protected function pricePayload(Product $product, ?array $stats, ?Post $review, bool $withPoints = false): array
    {
        $hasStats = (bool) ($stats['has_stats'] ?? false);

        $payload = [
            'product' => [
                'name' => $product->name,
                'brand' => $product->brand,
                'asin' => $product->asin,
            ],
            'currency' => 'USD',
            'current' => $stats['current'] ?? null,
            'checked_at' => $stats['checked_at'] ?? null,
            'tracking_since' => $stats['tracking_since'] ?? null,
            'has_stats' => $hasStats,
            'stats' => $hasStats ? [
                'low30' => $stats['low30'],
                'avg30' => $stats['avg30'],
                'high30' => $stats['high30'],
                'low90' => $stats['low90'],
                'avg90' => $stats['avg90'],
                'high90' => $stats['high90'],
            ] : null,
            'stats_unavailable_reason' => $hasStats ? null : $this->gateReason(),
            'verdict' => $stats['verdict'] ?? null,
            'drop_pct' => $stats['drop_pct'] ?? null,
        ];

        if ($withPoints) {
            $payload['history'] = array_map(
                fn (array $point) => ['date' => $point[0], 'price' => $point[1]],
                $stats['points'] ?? [],
            );
        }

        return $payload + [
            'review_url' => $review ? route('posts.show', $review->slug) : null,
            'review_title' => $review?->title,
            'affiliate_url' => $this->affiliateUrl($product->id, $review?->id),
            'disclosure' => $this->disclosure(),
            'methodology_url' => $this->methodologyUrl(),
            'note' => 'Tracked GadgetDrop price history — not a live Amazon price. Always confirm the final price at checkout.',
        ];
    }

    /**
     * Lean JSON text response (avoids the package's @internal Response::json).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function jsonResponse(array $payload): Response
    {
        return Response::text((string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
