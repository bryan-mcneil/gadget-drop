<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonProductService
{
    private bool   $configured;
    private string $accessKey;
    private string $secretKey;
    private string $partnerTag;
    private string $host;
    private string $region;

    public function __construct()
    {
        $this->accessKey  = config('services.amazon.pa_access_key') ?? '';
        $this->secretKey  = config('services.amazon.pa_secret_key') ?? '';
        $this->partnerTag = config('services.amazon.pa_partner_tag') ?? '';
        $this->host       = config('services.amazon.pa_host') ?? 'webservices.amazon.com';
        $this->region     = config('services.amazon.pa_region') ?? 'us-east-1';

        $this->configured = filled($this->accessKey)
                         && filled($this->secretKey)
                         && filled($this->partnerTag);
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Cached lookup — calls the API at most once per 12 hours per ASIN.
     * Returns null immediately if not configured; short-caches null on API failure.
     */
    public function cachedLookup(string $asin): ?array
    {
        if (! $this->configured) {
            return null;
        }

        $key = "amazon_product_{$asin}";

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $data = $this->lookup($asin);
        $ttl  = $data ? now()->addHours(12) : now()->addHour();
        Cache::put($key, $data, $ttl);

        return $data;
    }

    /**
     * Fetch product data for an ASIN.
     * Returns an array with name/price/description, or null if unavailable.
     */
    public function lookup(string $asin): ?array
    {
        if (! $this->configured) {
            return null;
        }

        try {
            $raw = $this->callApi($asin);
            return $this->parseItem($raw);
        } catch (\Throwable $e) {
            Log::warning('Amazon PA API lookup failed', ['asin' => $asin, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Private ─────────────────────────────────────────────────

    private function callApi(string $asin): array
    {
        $path    = '/paapi5/getitems';
        $target  = 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems';
        $service = 'ProductAdvertisingAPI';

        $payload = json_encode([
            'PartnerTag'  => $this->partnerTag,
            'PartnerType' => 'Associates',
            'ItemIds'     => [$asin],
            'Resources'   => [
                'ItemInfo.Title',
                'ItemInfo.Features',
                'Offers.Listings.Price',
            ],
        ]);

        $now        = now()->utc();
        $dateTime   = $now->format('Ymd\THis\Z');
        $dateStamp  = $now->format('Ymd');
        $contentType = 'application/json; charset=utf-8';

        $canonicalHeaders = implode("\n", [
            'content-encoding:amz-1.0',
            "content-type:{$contentType}",
            "host:{$this->host}",
            "x-amz-date:{$dateTime}",
            "x-amz-target:{$target}",
        ]) . "\n";

        $signedHeaders = 'content-encoding;content-type;host;x-amz-date;x-amz-target';

        $canonicalRequest = implode("\n", [
            'POST',
            $path,
            '',
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $payload),
        ]);

        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";

        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $dateTime,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($dateStamp);
        $signature  = bin2hex(hash_hmac('sha256', $stringToSign, $signingKey, true));

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, "
                       . "SignedHeaders={$signedHeaders}, Signature={$signature}";

        $response = Http::timeout(8)->withHeaders([
            'content-encoding' => 'amz-1.0',
            'content-type'     => $contentType,
            'host'             => $this->host,
            'x-amz-date'       => $dateTime,
            'x-amz-target'     => $target,
            'Authorization'    => $authorization,
        ])->post("https://{$this->host}{$path}", json_decode($payload, true));

        if (! $response->successful()) {
            throw new \RuntimeException("PA API HTTP {$response->status()}: {$response->body()}");
        }

        return $response->json();
    }

    private function parseItem(array $raw): ?array
    {
        $item = $raw['ItemsResult']['Items'][0] ?? null;

        if (! $item) {
            return null;
        }

        $name  = $item['ItemInfo']['Title']['DisplayValue'] ?? null;
        $price = $item['Offers']['Listings'][0]['Price']['Amount'] ?? null;

        $features = $item['ItemInfo']['Features']['DisplayValues'] ?? [];
        $description = $features ? implode("\n", array_slice($features, 0, 5)) : null;

        return [
            'name'        => $name,
            'price'       => $price,
            'description' => $description,
        ];
    }

    private function deriveSigningKey(string $dateStamp): string
    {
        $kDate    = hash_hmac('sha256', $dateStamp,               'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region,            $kDate,    true);
        $kService = hash_hmac('sha256', 'ProductAdvertisingAPI',  $kRegion,  true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
