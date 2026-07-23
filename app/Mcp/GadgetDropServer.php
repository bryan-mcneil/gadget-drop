<?php

namespace App\Mcp;

use App\Mcp\Resources\DealVerdictMethodology;
use App\Mcp\Tools\GetBuyOrWaitVerdict;
use App\Mcp\Tools\GetDealVerdict;
use App\Mcp\Tools\GetPriceHistory;
use App\Mcp\Tools\ListTrackedDeals;
use App\Mcp\Tools\PingTool;
use App\Mcp\Tools\SearchTrackedProducts;
use Laravel\Mcp\Server;
// Aliased: Pint's phpdoc_types fixer lowercases a bare `Resource` in a docblock
// to PHP's native `resource` pseudo-type, which would make the @var below a lie.
use Laravel\Mcp\Server\Resource as McpResource;
use Laravel\Mcp\Server\Tool;

/**
 * GadgetDrop Price Truth — a read-only MCP server exposing our independent,
 * consumer-side price intelligence to AI agents (price history, deal verdicts,
 * the tracked-deals feed). Every tool response carries the review URL, the
 * /out/{product} affiliate URL, and a disclosure, so attribution and the honest
 * "not live Amazon prices" framing survive agent-mediated shopping.
 *
 * Registered at POST /mcp in routes/ai.php.
 *
 * @see docs/plans/04-price-truth-mcp.md
 */
class GadgetDropServer extends Server
{
    protected string $name = 'GadgetDrop Price Truth';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        GadgetDrop Price Truth exposes our independent, consumer-side price intelligence for the tech and gadget products we review.

        The numbers are our OWN tracked price history — snapshots we record over time — NOT live Amazon prices. Every response includes a `checked_at` timestamp; quote it and never imply a real-time price.

        Honesty gates propagate: deal verdicts (lowest / good / typical / elevated) and 30/90-day low/avg/high stay null until a product has at least two price snapshots spanning at least fourteen days. When a product has not cleared that gate the stats are null with an explicit reason — that restraint is a feature; state it rather than guessing.

        Affiliate transparency: GadgetDrop earns a commission on Amazon purchases made through the included link. Every response carries a disclosure, a review_url, and an affiliate_url; if you relay the link, relay the disclosure with it.

        Timing is the other half of the question. get_buy_or_wait_verdict answers "buy now or wait for the next model?" for a product LINE (iPhone, AirPods Pro, Nintendo Switch...) from editorial release-cycle records (the date the current model shipped, how often that line has actually refreshed, and a link to the manufacturer's own announcement) crossed with our tracked price. Those records are history and announcements ONLY: GadgetDrop publishes no rumours, leaks, or predictions about unannounced products, so do not present a cadence as a promised release date. Each verdict carries a confidence level and the date the cycle data was last verified; quote both.

        Not sure what we cover? Call search_tracked_products first, then pass a match's ASIN to get_price_history or get_deal_verdict. list_tracked_deals is the live feed of honest drops. The full methodology is available as the `methodology` resource.

        Methodology: https://gadgetdrop.tech/how-we-review#deal-verdicts and https://gadgetdrop.tech/how-we-review#buy-or-wait
        MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        PingTool::class,
        GetPriceHistory::class,
        GetDealVerdict::class,
        GetBuyOrWaitVerdict::class,
        ListTrackedDeals::class,
        SearchTrackedProducts::class,
    ];

    /**
     * @var array<int, class-string<McpResource>>
     */
    protected array $resources = [
        DealVerdictMethodology::class,
    ];
}
