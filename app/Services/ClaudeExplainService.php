<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\TextDelta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Calls the Claude API to write a short, plain-English explanation of an
 * already-computed buy-or-wait verdict ({@see \App\Support\BuyOrWait}). This
 * class never computes or influences the verdict itself, only narrates one
 * that already exists, from a fixed set of facts the caller assembles.
 *
 * Same defensive shape as {@see CanopyApiService}: config-driven key,
 * isConfigured(), a database-cache-backed daily budget guard, and a null
 * return (never a thrown exception) on any failure so the caller always has
 * a graceful fallback to show instead of a raw error or a half sentence.
 */
class ClaudeExplainService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
    You are writing a short, plain-English explanation of a price/timing verdict for a gadget review site. You'll be given: the product name, its current price and when it was last checked, its 90-day average price, the verdict label, the confidence level, and cycle data (on-sale date, typical refresh interval, last time the cycle was checked).

    Write 2-3 sentences explaining why the verdict is what it is, using only the facts provided. Do not reference any price, date, or fact not included in the input. Do not predict future prices or product releases. Do not speculate about unannounced products or rumored launches. Do not use urgency language ("act now," "don't miss out"). If the confidence level is low, say so plainly and name what limits it (e.g., stale cycle data, insufficient price history). Match a direct, unhedged tone — state the reasoning, don't sell. Never use an em dash.
    PROMPT;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.claude.api_key') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Requests still allowed today under the self-imposed budget. */
    public function requestsRemainingToday(): int
    {
        $limit = (int) config('services.claude.daily_limit', 100);

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
     * Streams the explanation, calling $onDelta(string $htmlEscapedChunk) as
     * each piece of text arrives, and returns the full assembled (already
     * HTML-escaped) text. Returns null on any failure — not configured,
     * budget exhausted, auth, rate limit, network — so the caller shows a
     * graceful fallback rather than a raw error or a half-written sentence.
     *
     * Escaping happens here, not in the caller: the text is handed straight
     * to Livewire's stream(), which inserts it as raw HTML
     * (insertAdjacentHTML), so unescaped model output would be an XSS hole.
     *
     * @param  array<string, mixed>  $facts
     */
    public function streamExplanation(array $facts, callable $onDelta): ?string
    {
        if (! $this->isConfigured() || $this->requestsRemainingToday() < 1) {
            return null;
        }

        try {
            $client = new Client(apiKey: $this->apiKey);

            $stream = $client->messages->createStream(
                model: (string) config('services.claude.model', 'claude-haiku-4-5-20251001'),
                maxTokens: 400,
                system: self::SYSTEM_PROMPT,
                messages: [
                    [
                        'role' => 'user',
                        'content' => "Explain this verdict using only these facts:\n\n"
                            .json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
            );

            $this->countRequest();

            $full = '';

            foreach ($stream as $event) {
                if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $chunk = htmlspecialchars($event->delta->text, ENT_QUOTES, 'UTF-8');
                    $full .= $chunk;
                    $onDelta($chunk);
                }
            }

            return $full !== '' ? $full : null;
        } catch (AnthropicException $e) {
            Log::warning('Claude explain-verdict call failed', ['error' => $e->getMessage()]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Claude explain-verdict call failed unexpectedly', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Counted before the response streams back — an attempt that reaches the provider bills either way. */
    private function countRequest(): void
    {
        try {
            Cache::add($this->usageKey(), 0, now()->addDays(2));
            Cache::increment($this->usageKey());
        } catch (\Throwable) {
            // Cache unavailable — the daily budget check degrades to best-effort.
        }
    }

    private function usageKey(): string
    {
        return 'claude-explain.usage.'.now()->format('Y-m-d');
    }
}
