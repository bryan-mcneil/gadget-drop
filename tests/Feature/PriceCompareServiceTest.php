<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\PriceCompareService;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * Plan 11 Phase 11.2.
 *
 * NO TEST HERE MAY REACH THE REAL API. Every case injects a PSR-18 stub as the
 * SDK transporter, which also lets the compliance cases assert on the exact
 * bytes we would have put on the wire.
 */
class PriceCompareServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'price-compare.enabled' => true,
            'services.claude.api_key' => 'test-key',
            'price-compare.daily_limit' => 150,
        ]);
    }

    // ------------------------------------------------------------- the guards

    public function test_a_disabled_feature_never_calls_the_api(): void
    {
        config(['price-compare.enabled' => false]);

        $transport = $this->transport();

        $this->assertNull($this->service($transport)->compare($this->product()));
        $this->assertSame(0, $transport->calls);
    }

    public function test_a_missing_api_key_never_calls_the_api(): void
    {
        config(['services.claude.api_key' => '']);

        $transport = $this->transport();

        $this->assertNull($this->service($transport)->compare($this->product()));
        $this->assertSame(0, $transport->calls);
    }

    public function test_an_exhausted_daily_budget_never_calls_the_api(): void
    {
        config(['price-compare.daily_limit' => 2]);
        Cache::put('price-compare.usage.'.now()->format('Y-m-d'), 2, now()->addDay());

        $transport = $this->transport();

        $this->assertNull($this->service($transport)->compare($this->product()));
        $this->assertSame(0, $transport->calls);
    }

    public function test_the_budget_is_spent_before_the_call_so_a_failure_still_counts(): void
    {
        // An attempt that reaches the provider bills whether or not we like the
        // answer, so a failing call must not be a free retry loop.
        $transport = $this->transport(throw: true);

        $this->assertNull($this->service($transport)->compare($this->product()));
        $this->assertSame(1, $transport->calls);
        $this->assertSame(1, (int) Cache::get('price-compare.usage.'.now()->format('Y-m-d')));
    }

    // --------------------------------------------------------- the wire shape

    /**
     * THE COMPLIANCE TEST. The whitelist is what makes an Amazon price
     * structurally impossible (Associates 2(b)), so assert it actually reaches
     * the wire rather than trusting that the payload was assembled correctly.
     */
    public function test_the_request_carries_the_retailer_whitelist_and_never_amazon(): void
    {
        config(['price-compare.retailers' => ['walmart.com', 'bestbuy.com']]);

        $transport = $this->transport();
        $this->service($transport)->compare($this->product());

        $body = json_decode((string) $transport->body, true);
        $tool = $body['tools'][0];

        $this->assertSame('web_search_20250305', $tool['type']);
        $this->assertSame(['walmart.com', 'bestbuy.com'], $tool['allowed_domains']);
        $this->assertArrayNotHasKey(
            'blocked_domains',
            $tool,
            'allowed_domains and blocked_domains are mutually exclusive: sending both is a 400.'
        );
        $this->assertStringNotContainsStringIgnoringCase('amazon.com', (string) $transport->body);
    }

    public function test_the_request_uses_the_configured_model_effort_and_schema(): void
    {
        $transport = $this->transport();
        $this->service($transport)->compare($this->product());

        $body = json_decode((string) $transport->body, true);

        $this->assertSame('claude-sonnet-5', $body['model']);
        $this->assertSame('medium', $body['output_config']['effort']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame(4, $body['tools'][0]['max_uses']);
        $this->assertSame('US', $body['tools'][0]['user_location']['country']);
    }

    public function test_our_own_price_is_never_sent_to_the_model(): void
    {
        // Handing over the number the reader is comparing against invites the
        // model to report it back as somebody else's.
        $product = $this->product(['price' => 199.99]);

        $transport = $this->transport();
        $this->service($transport)->compare($product);

        $this->assertStringNotContainsString('199.99', (string) $transport->body);
    }

    /**
     * maxRetries: 0 is load-bearing: retries apply to timeouts, so the SDK
     * default of 2 would turn a 25s budget into a 75s wall clock and hand the
     * reader a 504 instead of our retry line.
     */
    public function test_a_transport_failure_is_not_retried(): void
    {
        $transport = $this->transport(status: 500);

        $this->assertNull($this->service($transport)->compare($this->product()));
        $this->assertSame(1, $transport->calls, 'A 500 must not be retried: the request budget is 25 seconds total.');
    }

    // ---------------------------------------------------------- the responses

    public function test_a_well_formed_response_decodes_into_the_raw_payload(): void
    {
        $payload = [
            'confidence' => 0.9,
            'retailers_checked' => 6,
            'results' => [[
                'retailer' => 'Walmart',
                'price' => 189.99,
                'url' => 'https://www.walmart.com/ip/1',
                'in_stock' => true,
                'exact_model_match' => true,
            ]],
        ];

        $result = $this->service($this->transport(payload: $payload))->compare($this->product());

        $this->assertSame($payload, $result);
    }

    public function test_a_paused_turn_is_a_failure_not_a_second_round_trip(): void
    {
        $transport = $this->transport(stopReason: 'pause_turn');

        $this->assertNull($this->service($transport)->compare($this->product()));
        $this->assertSame(1, $transport->calls);
    }

    public function test_a_truncated_or_refused_answer_is_a_failure(): void
    {
        $this->assertNull($this->service($this->transport(stopReason: 'max_tokens'))->compare($this->product()));
        $this->assertNull($this->service($this->transport(stopReason: 'refusal'))->compare($this->product()));
    }

    public function test_unparseable_output_returns_null_without_throwing(): void
    {
        $transport = $this->transport(text: 'Sorry, I could not find that product.');

        $this->assertNull($this->service($transport)->compare($this->product()));
    }

    public function test_a_response_with_no_text_block_at_all_returns_null(): void
    {
        $transport = $this->transport(blocks: []);

        $this->assertNull($this->service($transport)->compare($this->product()));
    }

    public function test_a_transport_exception_returns_null_without_throwing(): void
    {
        $transport = $this->transport(throw: true);

        $this->assertNull($this->service($transport)->compare($this->product()));
    }

    /**
     * Server tool errors come back HTTP 200 with an error object where the
     * result list would be, so they never raise. A failed search is not a
     * failed request: whatever the model still managed to report is usable.
     */
    public function test_a_web_search_error_block_does_not_discard_a_usable_answer(): void
    {
        $payload = ['confidence' => 0.7, 'retailers_checked' => 2, 'results' => []];

        $transport = $this->transport(blocks: [
            [
                'type' => 'web_search_tool_result',
                'tool_use_id' => 'srvtoolu_1',
                'caller' => ['type' => 'direct'],
                'content' => ['type' => 'web_search_tool_result_error', 'error_code' => 'max_uses_exceeded'],
            ],
            ['type' => 'text', 'text' => json_encode($payload)],
        ]);

        $this->assertSame($payload, $this->service($transport)->compare($this->product()));
    }

    public function test_a_thinking_block_before_the_answer_does_not_hide_it(): void
    {
        $payload = ['confidence' => 0.8, 'retailers_checked' => 3, 'results' => []];

        $transport = $this->transport(blocks: [
            ['type' => 'thinking', 'thinking' => 'Checking retailers.', 'signature' => 'sig'],
            ['type' => 'text', 'text' => json_encode($payload)],
        ]);

        $this->assertSame($payload, $this->service($transport)->compare($this->product()));
    }

    // ----------------------------------------------------------------- helpers

    private function service(ClientInterface $transport): PriceCompareService
    {
        return new PriceCompareService($transport);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<int, array<string, mixed>>|null  $blocks
     */
    private function transport(
        ?array $payload = null,
        ?string $text = null,
        ?array $blocks = null,
        string $stopReason = 'end_turn',
        int $status = 200,
        bool $throw = false,
    ): StubTransport {
        $payload ??= ['confidence' => 0.9, 'retailers_checked' => 4, 'results' => []];
        $blocks ??= [['type' => 'text', 'text' => $text ?? json_encode($payload)]];

        return new StubTransport(
            responseBody: json_encode([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-sonnet-5',
                'content' => $blocks,
                'stop_reason' => $stopReason,
                'stop_sequence' => null,
                'usage' => [
                    'input_tokens' => 1200,
                    'output_tokens' => 300,
                    'server_tool_use' => ['web_search_requests' => 4, 'web_fetch_requests' => 0],
                ],
            ]),
            status: $status,
            throw: $throw,
        );
    }

    /** @param  array<string, mixed>  $overrides */
    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'gadgets'],
            ['name' => 'Gadgets', 'description' => 'Test category.'],
        );

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'UGREEN NASync DXP2800',
            'brand' => 'UGREEN',
            'asin' => 'B0TESTASIN',
            'affiliate_url' => 'https://www.amazon.com/dp/B0TESTASIN',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 379.99,
            'description' => 'A test gadget.',
        ], $overrides));
    }
}

/** A PSR-18 client that never leaves the process and records what it was handed. */
class StubTransport implements ClientInterface
{
    public int $calls = 0;

    public ?string $body = null;

    public function __construct(
        private string $responseBody,
        private int $status = 200,
        private bool $throw = false,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->calls++;
        $this->body = (string) $request->getBody();

        if ($this->throw) {
            throw new \GuzzleHttp\Exception\ConnectException('offline', new PsrRequest('POST', 'https://api.anthropic.com'));
        }

        return new Response($this->status, ['Content-Type' => 'application/json'], $this->responseBody);
    }
}
