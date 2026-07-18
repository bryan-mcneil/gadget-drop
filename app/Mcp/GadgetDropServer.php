<?php

namespace App\Mcp;

use App\Mcp\Tools\PingTool;
use Laravel\Mcp\Server;

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

        Methodology: https://gadgetdrop.tech/how-we-review#deal-verdicts
        MARKDOWN;

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        PingTool::class,
    ];
}
