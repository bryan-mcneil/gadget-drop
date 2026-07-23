<?php

use App\Http\Middleware\McpCacheControl;
use App\Http\Middleware\McpPayloadCap;
use App\Http\Middleware\McpUsageMeter;
use App\Mcp\GadgetDropServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI / MCP Routes
|--------------------------------------------------------------------------
|
| The Price-Truth MCP server (docs/plans/04-price-truth-mcp.md). Read-only,
| public, throttled. laravel/mcp auto-loads this file OUTSIDE the `web`
| middleware group, so /mcp carries no session/CSRF middleware — exactly right
| for the stateless JSON-RPC endpoint agents POST to. GET/DELETE on the route
| answer 405 (handled by the package); only POST reaches the server.
|
*/

// throttle is intentionally outermost: it sheds excess load before the server
// does any work. It also *throws* ThrottleRequestsException, so a 429 unwinds
// past McpCacheControl either way — reordering could not add no-store to a 429
// (and the tiny POST 429 body carries no cacheable price data regardless).
// Inside it: McpCacheControl wraps the rest so the payload-cap 413 and every
// tool reply carry no-store; McpPayloadCap sheds oversized inputs before the
// meter counts them or the server parses them.
Mcp::web('/mcp', GadgetDropServer::class)
    ->middleware(['throttle:mcp', McpCacheControl::class, McpPayloadCap::class, McpUsageMeter::class]);
