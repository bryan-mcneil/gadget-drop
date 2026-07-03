<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Canopy API (canopyapi.co) — third-party Amazon product data used to refresh
 * prices while the site doesn't yet qualify for Amazon's own PA-API.
 *
 * Returns the SAME array shape as AmazonProductService::lookup() so
 * prices:refresh can treat the two sources interchangeably; the moment PA-API
 * credentials exist, that service wins and this one goes dormant.
 *
 * Cost control is structural: the account stays on the free Hobby tier
 * (100 requests/month, no card attached) and requestsRemainingThisMonth()
 * hard-stops at config('services.canopy.monthly_budget') so we can never even
 * hit the provider-side ceiling. Usage is counted on the database cache store.
 */
class CanopyApiService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.canopy.api_key') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Requests still allowed this calendar month under the self-imposed budget.
     */
    public function requestsRemainingThisMonth(): int
    {
        $budget = (int) config('services.canopy.monthly_budget', 90);

        return max(0, $budget - $this->usageThisMonth());
    }

    public function usageThisMonth(): int
    {
        try {
            return (int) Cache::get($this->usageKey(), 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Fetch product data for an ASIN, or null when unavailable/over budget.
     *
     * @return array{name: ?string, price: ?float, description: ?string, brand: ?string, amazon_rating: ?float, amazon_review_count: ?int, image_url: ?string}|null
     */
    public function lookup(string $asin): ?array
    {
        if (! $this->isConfigured() || $this->requestsRemainingThisMonth() < 1) {
            return null;
        }

        try {
            $raw = $this->callApi($asin);

            return $this->parseItem($raw);
        } catch (\Throwable $e) {
            Log::warning('Canopy API lookup failed', ['asin' => $asin, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function callApi(string $asin): array
    {
        $query = <<<'GRAPHQL'
        query GetProduct($asin: String!) {
            amazonProduct(input: { asinLookup: { asin: $asin } }) {
                title
                brand
                mainImageUrl
                rating
                ratingsTotal
                price {
                    value
                    currency
                }
                featureBullets
            }
        }
        GRAPHQL;

        $response = Http::timeout(10)
            ->withHeaders(['API-KEY' => $this->apiKey])
            ->post('https://graphql.canopyapi.co/', [
                'query'     => $query,
                'variables' => ['asin' => $asin],
            ]);

        // Count every attempt that reached the provider, success or error —
        // Canopy bills the request either way.
        $this->countRequest();

        if (! $response->successful()) {
            throw new \RuntimeException("Canopy API HTTP {$response->status()}: {$response->body()}");
        }

        $json = $response->json();

        if (! empty($json['errors'])) {
            throw new \RuntimeException('Canopy API error: ' . json_encode($json['errors']));
        }

        return $json;
    }

    private function parseItem(array $raw): ?array
    {
        $item = $raw['data']['amazonProduct'] ?? null;

        if (! $item) {
            return null;
        }

        $bullets     = $item['featureBullets'] ?? [];
        $description = $bullets ? implode("\n", array_slice((array) $bullets, 0, 5)) : null;

        return [
            'name'                => $item['title'] ?? null,
            'price'               => isset($item['price']['value']) ? (float) $item['price']['value'] : null,
            'description'         => $description,
            'brand'               => $item['brand'] ?? null,
            'amazon_rating'       => isset($item['rating']) ? (float) $item['rating'] : null,
            'amazon_review_count' => isset($item['ratingsTotal']) ? (int) $item['ratingsTotal'] : null,
            'image_url'           => $item['mainImageUrl'] ?? null,
        ];
    }

    private function countRequest(): void
    {
        try {
            // ~40-day TTL so the counter outlives its month, then gets pruned
            // by the daily cache:prune-expired task.
            Cache::add($this->usageKey(), 0, now()->addDays(40));
            Cache::increment($this->usageKey());
        } catch (\Throwable) {
            // Cache unavailable — the monthly budget check degrades to
            // best-effort; the daily --limit still bounds volume.
        }
    }

    private function usageKey(): string
    {
        return 'canopy.usage.' . now()->format('Y-m');
    }
}
