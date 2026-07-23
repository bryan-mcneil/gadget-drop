<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsPriceTruthResponses;
use App\Mcp\Support\ProductResolver;
use App\Support\PriceIntel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * "What can I even ask about?" — the discovery affordance. Matches name/brand
 * substrings (case-insensitive) or an exact ASIN across products with a
 * published review, and reports per-match whether the honesty-gated stats are
 * open yet.
 */
#[Name('search_tracked_products')]
#[Description('Search the products GadgetDrop tracks (name or brand substring, or exact Amazon ASIN). Returns up to 10 matches with each product\'s ASIN, review URL, and whether full price stats are available yet. Use this first when get_price_history or get_deal_verdict reports a product as not tracked.')]
class SearchTrackedProducts extends Tool
{
    use BuildsPriceTruthResponses;

    /** Max matches returned — discovery, not an export. */
    public const MAX_RESULTS = 10;

    public function handle(Request $request): Response
    {
        $query = trim((string) $request->get('query', ''));

        if (mb_strlen($query) < 2 || mb_strlen($query) > 120) {
            return Response::error('`query` is required (2–120 characters).');
        }

        $matches = ProductResolver::search($query, self::MAX_RESULTS)
            ->map(function ($product) {
                $review = ProductResolver::reviewFor($product);
                $stats = PriceIntel::stats($product->id);

                return [
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'asin' => $product->asin,
                    'has_stats' => (bool) ($stats['has_stats'] ?? false),
                    'review_url' => $review ? route('posts.show', $review->slug) : null,
                ];
            })
            ->values()
            ->all();

        return $this->jsonResponse([
            'count' => count($matches),
            'matches' => $matches,
            'disclosure' => $this->disclosure(),
            'methodology_url' => $this->methodologyUrl(),
            'note' => $matches === []
                ? 'No tracked product matched. GadgetDrop only covers products we have published a review for.'
                : 'Pass a match\'s asin to get_price_history or get_deal_verdict for the full picture.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->min(2)
                ->max(120)
                ->description('Product name or brand substring, or an exact Amazon ASIN.')
                ->required(),
        ];
    }
}
