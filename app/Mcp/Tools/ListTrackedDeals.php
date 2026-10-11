<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsPriceTruthResponses;
use App\Support\DealsFeed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * The live qualifying-deals feed — the exact array /deals renders (shared
 * builder + 1h cache in App\Support\DealsFeed). Honesty gates are already
 * applied upstream: a product appears only with ≥5% below its tracked 90-day
 * average, a lowest/good verdict, and a published review. An empty list is a
 * truthful list.
 */
#[Name('list_tracked_deals')]
#[Description('List the products currently at least 5% below their own tracked 90-day average price — the same feed as gadgetdrop.tech/deals. Only products with a published GadgetDrop review and enough price history qualify, so an empty list means no honest deals right now, not missing data.')]
class ListTrackedDeals extends Tool
{
    use BuildsPriceTruthResponses;

    public function handle(Request $request): Response
    {
        $limit = (int) ($request->get('limit') ?? DealsFeed::MAX_ENTRIES);
        $limit = max(1, min(DealsFeed::MAX_ENTRIES, $limit));

        $deals = array_map(fn (array $deal) => [
            'name' => $deal['name'],
            'currency' => 'USD',
            'current' => $deal['current'],
            'typical_90d' => $deal['typical'],
            'low30' => $deal['low30'] ?? null,
            'low90' => $deal['low90'],
            'drop_pct' => $deal['drop_pct'],
            'verdict' => $deal['verdict'],
            'checked_at' => $deal['checked_at'],
            'worth_it_pct' => $deal['worth_pct'] ?? null,
            'worth_it_votes' => $deal['worth_total'] ?? 0,
            'review_url' => route('posts.show', $deal['post_slug']),
            'review_title' => $deal['post_title'],
            'affiliate_url' => $this->affiliateUrl($deal['product_id'], $deal['post_id']),
        ], array_slice(DealsFeed::get(), 0, $limit));

        return $this->jsonResponse([
            'count' => count($deals),
            'deals' => $deals,
            'deals_page' => route('deals'),
            'disclosure' => $this->disclosure(),
            'methodology_url' => $this->methodologyUrl(),
            'note' => $deals === []
                ? 'No products currently qualify — our tracked history shows no honest drops right now. That restraint is the product.'
                : 'Prices are our tracked history, not live Amazon prices; each entry carries its checked_at.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->min(1)
                ->max(DealsFeed::MAX_ENTRIES)
                ->description('Maximum entries to return (1–24, default 24).'),
        ];
    }
}
