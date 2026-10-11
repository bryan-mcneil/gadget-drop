<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject oversized tool inputs before the MCP server does any work. Legitimate
 * tool arguments here are an ASIN, a short query, or a limit — anything past
 * 1 KB is malformed or abusive, and shared-hosting CPU is the resource being
 * protected. Returns a JSON-RPC-shaped 413 so well-behaved clients can parse it.
 */
class McpPayloadCap
{
    /** Max serialized size of a tools/call `arguments` object. */
    public const MAX_ARGUMENT_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $arguments = $request->input('params.arguments');

        if ($arguments !== null && strlen((string) json_encode($arguments)) > self::MAX_ARGUMENT_BYTES) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => [
                    'code' => -32602,
                    'message' => 'Tool arguments exceed the 1 KB limit.',
                ],
            ], 413);
        }

        return $next($request);
    }
}
