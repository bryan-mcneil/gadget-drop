<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\UserLocation;
use Anthropic\Messages\WebSearchTool20250305;
use Anthropic\Messages\WebSearchToolResultBlock;
use Anthropic\Messages\WebSearchToolResultError;
use Anthropic\RequestOptions;
use App\Models\Product;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientInterface;

/**
 * Asks Claude to look up what a product currently costs at other major US
 * retailers, using the web search server tool, and returns the raw structured
 * payload. It does not rank, compare, or draw a conclusion: that is
 * {@see \App\Support\PriceComparison}'s job, in PHP, so that nothing the
 * reader sees as a factual claim is model prose.
 *
 * Same defensive shape as {@see ClaudeExplainService}: config-driven key,
 * isConfigured(), a database-cache-backed daily budget guard, and a null
 * return on ANY failure whatsoever so the caller always has a graceful
 * fallback to render. This class never throws.
 *
 * Two things here are load-bearing and must not be "simplified":
 *
 *  1. THE WHITELIST. allowed_domains is what makes an Amazon price
 *     structurally impossible (Associates 2(b): prices only from an
 *     Amazon-served link or PA-API). The tools payload is built from typed SDK
 *     objects rather than a hand-written array on purpose. A raw array is
 *     passed to the wire VERBATIM AND UNVALIDATED by this SDK, so a single
 *     typo ("allowed_domain") would silently ship a search with no whitelist
 *     at all, and the first symptom would be an Amazon price on the page.
 *     With the typed object a typo is a PHP error instead.
 *  2. THE DEADLINE, WHICH LIVES ON THE TRANSPORTER. There is no queue worker
 *     on this host, so this runs inside the reader's request and must fail
 *     before PHP and LiteSpeed do. Two separate traps here:
 *     (a) RequestOptions::timeout is DEAD in this SDK. It is declared and
 *         never read, and PSR-18's sendRequest() takes no timeout argument, so
 *         it could not be honoured anyway. The real deadline is configured on
 *         the Guzzle client in transporter().
 *     (b) maxRetries DOES bind, but only per call: SdkParams::parseRequest()
 *         mints a fresh RequestOptions whose required properties carry the
 *         defaults, so anything set on the Client constructor is overridden.
 *         Without maxRetries: 0 the deadline is spent three times over.
 *     Guard test: test_a_transport_failure_is_not_retried.
 *
 * @see docs/plans/11-live-price-compare.md Phase 11.2
 */
class PriceCompareService
{
    /**
     * Deliberately never mentions Amazon, and is never told our own price: an
     * anchor is exactly what you do not want in front of a model whose job is
     * to report numbers it read somewhere else.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
    You look up the current retail price of one specific product at major US retailers, using web search, and return structured data. You are a data collector, not an advisor.

    Rules:
    - Only report a listing if it is for the EXACT product named, sold by the retailer itself on the retailer's own site.
    - Set exact_model_match to false for ANY difference: a different capacity, size, colour, generation, model number, region, or a bundle/multi-pack. When in doubt, set it to false.
    - Report the item's own current price. Not a bundle price, not a subscription price, not a price that requires a membership or a trade-in, not a strikethrough list price.
    - Never report a price you did not actually read on the retailer's page. If you cannot read a price, set price to null.
    - Set in_stock to false when the listing is sold out, backordered, or unavailable.
    - Do not mention, search for, or report Amazon.
    - Do not rank, compare, total, recommend, or comment on whether anything is a good deal. Return data only.
    - Set confidence to how sure you are that the listings you found are the same product that was named. Low confidence is a useful answer.
    - Finding nothing is a valid and useful result. Return an empty results array rather than a guess.
    - Never use an em dash.
    PROMPT;

    /**
     * additionalProperties:false throughout so the model cannot smuggle an
     * extra field past PriceComparison's filters.
     */
    private const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['confidence', 'retailers_checked', 'results'],
        'properties' => [
            'confidence' => [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 1,
                'description' => 'How confident you are that these listings are the exact product named.',
            ],
            'retailers_checked' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'How many distinct retailer sites you actually looked at.',
            ],
            'results' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['retailer', 'price', 'url', 'in_stock', 'exact_model_match'],
                    'properties' => [
                        'retailer' => ['type' => 'string', 'description' => 'Retailer name as a shopper would say it, e.g. Best Buy.'],
                        'price' => ['type' => ['number', 'null'], 'description' => 'Current price in USD, or null if unreadable.'],
                        'url' => ['type' => 'string', 'description' => 'Direct URL of the product page you read the price on.'],
                        'in_stock' => ['type' => 'boolean'],
                        'exact_model_match' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ],
    ];

    private string $apiKey;

    /**
     * The transporter seam exists so tests can never reach the real API. In
     * production it is null and {@see self::transporter()} builds a Guzzle
     * client carrying the deadline.
     */
    public function __construct(private ?ClientInterface $transporter = null)
    {
        $this->apiKey = (string) (config('services.claude.api_key') ?? '');
    }

    /** The kill switch, independent of the API key the explain feature shares. */
    public function isEnabled(): bool
    {
        return (bool) config('price-compare.enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Uncached requests still allowed today under the self-imposed budget. */
    public function requestsRemainingToday(): int
    {
        $limit = (int) config('price-compare.daily_limit', 150);

        return max(0, $limit - $this->usageToday());
    }

    public function usageToday(): int
    {
        try {
            return (int) Cache::get($this->usageKey(), 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * The raw decoded model payload, or null on any failure at all: disabled,
     * unconfigured, out of budget, auth, rate limit, network, timeout,
     * pause_turn, refusal, truncation, or unparseable output.
     *
     * Returning null rather than throwing is the contract the Livewire caller
     * depends on: it shows a quiet retry line and, critically, writes NOTHING
     * to the cache, so an immediate retry is free to succeed.
     *
     * @return array<string, mixed>|null
     */
    public function compare(Product $product): ?array
    {
        if (! $this->isEnabled() || ! $this->isConfigured() || $this->requestsRemainingToday() < 1) {
            return null;
        }

        try {
            $client = new Client(
                apiKey: $this->apiKey,
                requestOptions: RequestOptions::with(transporter: $this->transporter()),
            );

            // maxRetries MUST be passed per call, not on the Client. Verified
            // against anthropic-ai/sdk in this repo: SdkParams::parseRequest()
            // builds a fresh RequestOptions for every call, and because
            // maxRetries is a required property with a default it is always
            // present in it (2) and therefore always wins the merge over
            // anything set on the Client. Setting it on the constructor looks
            // right and does nothing. Optional properties like transporter are
            // absent when unset, which is why THAT one still binds there.
            //
            // timeout is passed here for intent and forward compatibility only.
            // THE SDK NEVER READS IT: grep the package, RequestOptions::timeout
            // is declared and never consumed, and PSR-18's sendRequest() takes
            // no timeout argument, so it cannot be. The real deadline is set on
            // the Guzzle transporter in transporter() below. Do not delete that
            // and assume this line is doing the work.
            $requestOptions = RequestOptions::with(
                timeout: $this->timeout(),
                maxRetries: 0,
            );

            // Counted BEFORE the call: an attempt that reaches the provider
            // bills whether or not we like the answer.
            $this->countRequest();

            $message = $client->messages->create(
                model: (string) config('price-compare.model', 'claude-sonnet-5'),
                // Thinking tokens count against this, so it is sized well above
                // what the JSON itself needs.
                maxTokens: 8000,
                system: self::SYSTEM_PROMPT,
                messages: [[
                    'role' => 'user',
                    'content' => json_encode($this->facts($product), JSON_UNESCAPED_SLASHES),
                ]],
                tools: [
                    WebSearchTool20250305::with(
                        allowedDomains: $this->retailers(),
                        maxUses: (int) config('price-compare.max_uses', 4),
                        userLocation: UserLocation::with(country: 'US'),
                    ),
                ],
                outputConfig: OutputConfig::with(
                    effort: (string) config('price-compare.effort', 'medium'),
                    format: JSONOutputFormat::with(schema: self::SCHEMA),
                ),
                requestOptions: $requestOptions,
            );
        } catch (AnthropicException $e) {
            Log::warning('Price compare call failed', ['product' => $product->id, 'error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Price compare call failed unexpectedly', ['product' => $product->id, 'error' => $e->getMessage()]);

            return null;
        }

        return $this->payload($message, $product);
    }

    /**
     * Pull the structured payload out of the response, or null.
     *
     * @param  \Anthropic\Messages\Message  $message
     * @return array<string, mixed>|null
     */
    private function payload(object $message, Product $product): ?array
    {
        $this->logUsage($message, $product);

        // pause_turn would need another round trip we have no latency budget
        // for; refusal and max_tokens mean there is no complete answer to read.
        if (in_array($message->stopReason, ['pause_turn', 'refusal', 'max_tokens'], true)) {
            Log::warning('Price compare returned no usable answer', [
                'product' => $product->id,
                'stop_reason' => $message->stopReason,
            ]);

            return null;
        }

        $text = null;

        foreach ($message->content as $block) {
            // Server tool errors come back HTTP 200 with an error object where
            // the result list would be, so they never raise. A failed search is
            // not a failed request: partial results are still worth showing.
            if ($block instanceof WebSearchToolResultBlock && $block->content instanceof WebSearchToolResultError) {
                Log::warning('Price compare web search errored', [
                    'product' => $product->id,
                    'error_code' => $block->content->errorCode ?? null,
                ]);

                continue;
            }

            // Thinking blocks can precede the answer, so take the first text
            // block rather than assuming position.
            if ($text === null && $block instanceof TextBlock) {
                $text = $block->text;
            }
        }

        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::warning('Price compare returned unparseable output', ['product' => $product->id]);

            return null;
        }

        return $decoded;
    }

    /**
     * The only facts the model gets. Our own price is deliberately NOT among
     * them: it is the number the reader is comparing against, and handing it
     * over invites a model to report it back as somebody else's.
     *
     * @return array<string, mixed>
     */
    private function facts(Product $product): array
    {
        return array_filter([
            'product_name' => $product->name,
            'brand' => $product->brand,
            'amazon_asin' => $product->asin,
            'today' => now()->toDateString(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The transporter, and with it the only deadline that actually binds.
     *
     * There is no queue worker on this host, so this call happens inside the
     * reader's request: if it outlives PHP's max_execution_time or LiteSpeed's
     * proxy timeout, the reader gets a dead 504 instead of our retry line.
     * Guzzle is therefore constructed with an explicit total and connect
     * timeout rather than left to its defaults (no total timeout at all).
     *
     * A timed-out request surfaces as a Guzzle ConnectException, which the SDK
     * wraps in APIConnectionException, which compare() catches. Paired with
     * maxRetries: 0 so the budget is spent once, not three times over.
     */
    private function transporter(): ClientInterface
    {
        return $this->transporter ??= new GuzzleClient([
            'timeout' => $this->timeout(),
            'connect_timeout' => min(5.0, $this->timeout()),
            'http_errors' => false,
        ]);
    }

    private function timeout(): float
    {
        return (float) config('price-compare.timeout', 25.0);
    }

    /** @return array<int, string> */
    private function retailers(): array
    {
        $retailers = config('price-compare.retailers', []);

        return array_values(array_filter(is_array($retailers) ? $retailers : [], 'is_string'));
    }

    /**
     * Real spend, not the estimate. web_search_requests is the number that
     * actually bills at $10/1,000, so log it from day one rather than guessing
     * from the click count.
     *
     * @param  \Anthropic\Messages\Message  $message
     */
    private function logUsage(object $message, Product $product): void
    {
        Log::info('Price compare usage', [
            'product' => $product->id,
            'input_tokens' => $message->usage->inputTokens ?? null,
            'output_tokens' => $message->usage->outputTokens ?? null,
            'web_search_requests' => $message->usage->serverToolUse?->webSearchRequests ?? 0,
        ]);
    }

    private function countRequest(): void
    {
        try {
            Cache::add($this->usageKey(), 0, now()->addDays(2));
            Cache::increment($this->usageKey());
        } catch (\Throwable) {
            // Cache unavailable: the daily budget degrades to best-effort.
        }
    }

    private function usageKey(): string
    {
        return 'price-compare.usage.'.now()->format('Y-m-d');
    }
}
