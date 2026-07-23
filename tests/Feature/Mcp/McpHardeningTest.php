<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 4.4 — the usage meter (/gd-health reads mcp.calls.{tool}.{date}) and
 * the 1 KB tool-argument payload cap.
 */
class McpHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function callTool(string $name, array $arguments = [])
    {
        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);
    }

    public function test_tool_calls_are_counted_per_tool_per_day(): void
    {
        $key = 'mcp.calls.ping.'.now()->format('Y-m-d');

        $this->callTool('ping')->assertOk();
        $this->callTool('ping')->assertOk();

        $this->assertSame(2, Cache::get($key));
    }

    public function test_non_tool_calls_are_not_counted(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk();

        $this->assertNull(Cache::get('mcp.calls.ping.'.now()->format('Y-m-d')));
    }

    public function test_hostile_tool_names_become_safe_bounded_cache_keys(): void
    {
        // The name is client-supplied; the meter must sanitize it before it
        // becomes a cache-key segment (the call itself 200s with an MCP
        // "tool not found" JSON-RPC error — that's the package's concern).
        $this->callTool('../{evil} name!');

        $this->assertSame(1, Cache::get('mcp.calls.evilname.'.now()->format('Y-m-d')));
    }

    public function test_oversized_tool_arguments_are_rejected_with_413(): void
    {
        $response = $this->callTool('get_price_history', ['query' => str_repeat('a', 1100)]);

        $response->assertStatus(413)
            ->assertJsonPath('error.code', -32602);

        // Sheds before the meter: an over-cap request is never counted...
        $this->assertNull(Cache::get('mcp.calls.get_price_history.'.now()->format('Y-m-d')));
        // ...and still carries the endpoint's no-store contract.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_normal_sized_arguments_pass_the_cap(): void
    {
        $this->callTool('search_tracked_products', ['query' => 'headphones'])->assertOk();
    }
}
