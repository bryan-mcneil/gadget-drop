<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts tools/call requests per tool per day on the (database) cache store:
 * `mcp.calls.{tool}.{Y-m-d}`, 35-day TTL. /gd-health reads the last 7 days —
 * growth here is the leading indicator the AI-era bet is paying off. No PII,
 * no new table, and metering must never break the endpoint.
 */
class McpUsageMeter
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->input('method') === 'tools/call') {
            // Sanitize the (client-supplied) tool name so it is always a safe,
            // bounded cache-key segment; junk names still count (they cost us
            // CPU too) but can't grow past the throttle's 300/day/IP ceiling.
            $tool = substr(
                preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->input('params.name')) ?: 'unknown',
                0,
                40,
            );

            $key = 'mcp.calls.'.$tool.'.'.now()->format('Y-m-d');

            try {
                Cache::add($key, 0, now()->addDays(35));
                Cache::increment($key);
            } catch (\Throwable) {
                // A broken cache layer must not take the endpoint down with it.
            }
        }

        return $response;
    }
}
