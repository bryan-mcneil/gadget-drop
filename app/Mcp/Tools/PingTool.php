<?php

namespace App\Mcp\Tools;

use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Connectivity probe — proves the pipe end to end. No inputs, no price data;
 * the real price tools land in Phase 4.2. Named "ping" explicitly so the wire
 * name is not the kebab-cased class basename ("ping-tool").
 */
#[Name('ping')]
#[Description('Connectivity check for the GadgetDrop Price Truth server: returns the current server time and how many products GadgetDrop is tracking. Call this first to confirm the server is reachable.')]
class PingTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text(sprintf(
            'GadgetDrop Price Truth is online. Server time %s; tracking %d products.',
            now()->toIso8601String(),
            Product::count(),
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
