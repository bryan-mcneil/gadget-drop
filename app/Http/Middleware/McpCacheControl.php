<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force `Cache-Control: no-store` on every MCP response. The /mcp endpoint only
 * answers POST (JSON-RPC), which the Hostinger CDN (hcdn) / LiteSpeed will not
 * cache anyway — but we say so explicitly: tool replies embed a `checked_at`
 * timestamp and a live product count and must never be served stale from an
 * intermediary.
 */
class McpCacheControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
