<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Search Console API — the three endpoints we actually use: Search
 * Analytics (query/page/date data), Sitemaps (re-nudge after publish), and URL
 * Inspection (per-URL index status).
 *
 * Auth is a service account (JSON key added as a user on the GSC property):
 * google/auth mints a short-lived bearer token, cached on the default cache
 * store until just before expiry. Everything degrades to a logged no-op
 * (returns null) when the property or key is missing, so local dev and CI never
 * need credentials — mirrors CanopyApiService discipline.
 */
class GoogleSearchConsoleService
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters';

    private const TOKEN_KEY = 'gsc.token';

    private const ROW_LIMIT = 25000;

    public function isConfigured(): bool
    {
        return (string) config('services.google_search_console.property') !== '';
    }

    public function property(): string
    {
        return (string) config('services.google_search_console.property');
    }

    /**
     * A valid bearer token, or null when GSC is unconfigured / the key is
     * unreadable. Cached until 5 minutes before expiry. Tests seed TOKEN_KEY
     * directly so the (un-fakeable) google/auth handshake never runs under CI.
     */
    public function token(): ?string
    {
        try {
            if ($cached = Cache::get(self::TOKEN_KEY)) {
                return $cached;
            }
        } catch (\Throwable) {
            // Cache not bound — fall through to a live mint.
        }

        if (! $this->isConfigured()) {
            return null;
        }

        $key = $this->credentials();

        if ($key === null) {
            Log::warning('GSC credentials file missing/unreadable', [
                'path' => config('services.google_search_console.credentials_path'),
            ]);

            return null;
        }

        try {
            $creds = new ServiceAccountCredentials(self::SCOPE, $key);
            // Bound the OAuth handshake explicitly — google/auth's default Guzzle
            // client has no timeout, and this mint runs inline on the admin-publish
            // path (PostObserver). A hung Google token endpoint must never block a
            // save beyond a few seconds; everything else here is already bounded.
            $handler = HttpHandlerFactory::build(new Client(['timeout' => 5, 'connect_timeout' => 3]));
            $auth = $creds->fetchAuthToken($handler);
            $token = $auth['access_token'] ?? null;

            if ($token === null) {
                return null;
            }

            $ttl = max(60, (int) ($auth['expires_in'] ?? 3600) - 300);

            try {
                Cache::put(self::TOKEN_KEY, $token, now()->addSeconds($ttl));
            } catch (\Throwable) {
                // Best effort — a token that can't be cached is minted next call.
            }

            return $token;
        } catch (\Throwable $e) {
            Log::warning('GSC token mint failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * POST searchAnalytics/query with automatic startRow pagination. Returns the
     * merged rows array (['rows' => [...]]) or null when unconfigured.
     *
     * @param  array<string, mixed>  $body  dimensions/searchType/startDate/endDate/... (rowLimit/startRow/dataState are managed here)
     * @return array{rows: array<int, array<string, mixed>>}|null
     */
    public function searchAnalytics(array $body): ?array
    {
        $token = $this->token();

        if ($token === null) {
            Log::info('GSC searchAnalytics skipped — not configured.');

            return null;
        }

        $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            .rawurlencode($this->property()).'/searchAnalytics/query';

        $body['dataState'] = $body['dataState'] ?? 'all';

        $rows = [];
        $startRow = 0;

        do {
            $page = array_merge($body, ['rowLimit' => self::ROW_LIMIT, 'startRow' => $startRow]);

            $json = $this->send('post', $endpoint, $token, $page);

            if ($json === null) {
                break;
            }

            $batch = $json['rows'] ?? [];
            $rows = array_merge($rows, $batch);
            $startRow += count($batch);

            // Stop when the API returns a short page (or an empty one).
        } while (count($batch) === self::ROW_LIMIT && $startRow < 500000);

        return ['rows' => $rows];
    }

    /**
     * Re-submit the sitemap so Google re-crawls it after a publish. Returns the
     * HTTP status code, or null when unconfigured.
     */
    public function submitSitemap(?string $sitemapUrl = null): ?int
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        $sitemapUrl ??= config('search.sitemap_url');

        $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'
            .rawurlencode($this->property()).'/sitemaps/'.rawurlencode($sitemapUrl);

        try {
            $resp = Http::timeout(10)->withToken($token)->put($endpoint);

            return $resp->status();
        } catch (\Throwable $e) {
            Log::warning('GSC submitSitemap failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Inspect a single URL's index status. Returns the decoded response, or null.
     *
     * @return array<string, mixed>|null
     */
    public function inspectUrl(string $url): ?array
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        return $this->send('post', 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', $token, [
            'inspectionUrl' => $url,
            'siteUrl' => $this->property(),
        ]);
    }

    /**
     * One HTTP call with bounded retry/backoff on 429/5xx. Returns decoded JSON
     * or null (logged) on persistent failure.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function send(string $method, string $url, string $token, array $body): ?array
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $resp = Http::timeout(30)->withToken($token)->{$method}($url, $body);
            } catch (\Throwable $e) {
                if ($attempt === 4) {
                    Log::warning('GSC request threw', ['url' => $url, 'error' => $e->getMessage()]);

                    return null;
                }
                $this->backoff($attempt);

                continue;
            }

            if ($resp->successful()) {
                return $resp->json();
            }

            if (in_array($resp->status(), [429, 500, 502, 503, 504], true) && $attempt < 4) {
                $this->backoff($attempt);

                continue;
            }

            Log::warning('GSC request failed', ['url' => $url, 'status' => $resp->status(), 'body' => $resp->body()]);

            return null;
        }

        return null;
    }

    private function backoff(int $attempt): void
    {
        // No real sleep under tests (they never hit the retry path anyway).
        if (! app()->runningUnitTests()) {
            sleep(min(8, 2 ** $attempt));
        }
    }

    /**
     * Decoded service-account key array, or null when the file is unreadable.
     *
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $path = (string) config('services.google_search_console.credentials_path');

        if ($path === '') {
            return null;
        }

        // Resolve relative paths against the app root (env stores a relative path).
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
