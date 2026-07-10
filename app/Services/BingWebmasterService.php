<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bing Webmaster API — free, single per-user API key passed as a query param.
 * We read performance data (rank/traffic, per-query stats) and, for enrichment,
 * keyword volume. No writes: Bing itself steers publishers to IndexNow, so the
 * URL Submission API is deliberately skipped.
 *
 * All responses wrap the payload in a top-level `d`. Shapes are normalised here
 * defensively — a shape we didn't expect degrades to an empty result (never an
 * exception), so a Bing hiccup can't break search:sync's Google path. No-ops
 * (returns []/null) when the key is unset.
 */
class BingWebmasterService
{
    private const BASE = 'https://ssl.bing.com/webmaster/api.svc/json/';

    public function isConfigured(): bool
    {
        return (string) config('services.bing_webmaster.api_key') !== '';
    }

    private function siteUrl(): string
    {
        return rtrim((string) config('services.bing_webmaster.site_url'), '/').'/';
    }

    /**
     * Daily clicks/impressions trend.
     *
     * @return array<int, array{date: string, clicks: int, impressions: int, position: ?float}>
     */
    public function rankAndTrafficStats(): array
    {
        $d = $this->get('GetRankAndTrafficStats');

        if ($d === null) {
            return [];
        }

        // Bing returns either a list of daily rows or an object carrying a
        // TrafficStats list — accept both.
        $rows = array_is_list($d) ? $d : ($d['TrafficStats'] ?? $d['Stats'] ?? []);

        $out = [];
        foreach ((array) $rows as $row) {
            $date = $this->parseDate($row['Date'] ?? null);

            if ($date === null) {
                continue;
            }

            $out[] = [
                'date' => $date->toDateString(),
                'clicks' => (int) ($row['Clicks'] ?? 0),
                'impressions' => (int) ($row['Impressions'] ?? 0),
                'position' => isset($row['AvgImpressionPosition']) ? (float) $row['AvgImpressionPosition'] : null,
            ];
        }

        return $out;
    }

    /**
     * Per-query rolling aggregate (~6 months, no date range).
     *
     * @return array<int, array{query: string, clicks: int, impressions: int, avg_click_position: ?float, avg_impression_position: ?float}>
     */
    public function queryStats(): array
    {
        $d = $this->get('GetQueryStats');

        if ($d === null) {
            return [];
        }

        $rows = array_is_list($d) ? $d : ($d['QueryStats'] ?? []);

        $out = [];
        foreach ((array) $rows as $row) {
            $query = trim((string) ($row['Query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $out[] = [
                'query' => $query,
                'clicks' => (int) ($row['Clicks'] ?? 0),
                'impressions' => (int) ($row['Impressions'] ?? 0),
                'avg_click_position' => isset($row['AvgClickPosition']) ? (float) $row['AvgClickPosition'] : null,
                'avg_impression_position' => isset($row['AvgImpressionPosition']) ? (float) $row['AvgImpressionPosition'] : null,
            ];
        }

        return $out;
    }

    /**
     * Keyword impression volume (free demand data) for one phrase, or null.
     *
     * @return array<string, mixed>|null
     */
    public function keyword(string $q, string $country = 'us', string $language = 'en-US'): ?array
    {
        return $this->get('GetKeyword', [
            'q' => $q,
            'country' => $country,
            'language' => $language,
        ]);
    }

    /**
     * Related-keyword expansion for one phrase, or null.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function relatedKeywords(string $q, string $country = 'us', string $language = 'en-US'): ?array
    {
        $d = $this->get('GetRelatedKeywords', [
            'q' => $q,
            'country' => $country,
            'language' => $language,
        ]);

        if ($d === null) {
            return null;
        }

        return array_is_list($d) ? $d : (array) ($d['Keywords'] ?? []);
    }

    /**
     * One GET against a Bing method. Returns the unwrapped `d` payload or null.
     *
     * @param  array<string, string>  $params
     * @return array<mixed>|null
     */
    private function get(string $method, array $params = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $resp = Http::timeout(15)->get(self::BASE.$method, array_merge([
                'apikey' => config('services.bing_webmaster.api_key'),
                'siteUrl' => $this->siteUrl(),
            ], $params));

            if (! $resp->successful()) {
                Log::warning('Bing API request failed', ['method' => $method, 'status' => $resp->status()]);

                return null;
            }

            $d = $resp->json('d');

            return is_array($d) ? $d : null;
        } catch (\Throwable $e) {
            // Guzzle connection errors embed the full effective URL (with ?apikey=…)
            // in the message; redact the key before it reaches the log.
            $message = preg_replace('/apikey=[^&\s]+/i', 'apikey=REDACTED', $e->getMessage());
            Log::warning('Bing API request threw', ['method' => $method, 'error' => $message]);

            return null;
        }
    }

    /**
     * Parse a WCF `/Date(ms)/` string (or an ISO string) into a Carbon date.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/\/Date\((\-?\d+)/', $value, $m)) {
            return Carbon::createFromTimestampMs((int) $m[1]);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
