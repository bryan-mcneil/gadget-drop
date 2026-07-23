<?php

namespace App\Mcp;

use App\Mcp\Resources\DealVerdictMethodology;
use App\Mcp\Tools\GetDealVerdict;
use App\Mcp\Tools\GetPriceHistory;
use App\Mcp\Tools\ListTrackedDeals;
use App\Mcp\Tools\PingTool;
use App\Mcp\Tools\SearchTrackedProducts;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Resource;
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

        Not sure what we cover? Call search_tracked_products first, then pass a match's ASIN to get_price_history or get_deal_verdict. list_tracked_deals is the live feed of honest drops. The full methodology is available as the `methodology` resource.

        Methodology: https://gadgetdrop.tech/how-we-review#deal-verdicts
        MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        PingTool::class,
        GetPriceHistory::class,
        GetDealVerdict::class,
        ListTrackedDeals::class,
        SearchTrackedProducts::class,
    ];

    /**
     * @var array<int, class-string<Resource>>
     */
    protected array $resources = [
        DealVerdictMethodology::class,
    ];
}
