<?php

namespace App\Services;

use App\Models\SearchSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow — one POST fans a URL list out to Bing, Yandex, Seznam, Naver and
 * Yep (Google does not participate; it gets sitemap pings instead). The key is
 * self-generated, served at /indexnow.txt, and passed as keyLocation.
 *
 * Every submission is logged to search_submissions; failures are swallowed so a
 * ping can never block the save that triggered it. No-op unless both the master
 * ping switch and a key are set, so local/dev/test never phone home.
 */
class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** IndexNow accepts up to 10,000 URLs/POST; we chunk far below that. */
    private const CHUNK = 500;

    /**
     * Announce URLs to the IndexNow network.
     *
     * @param  array<int, string>  $urls
     */
    public function submit(array $urls, string $trigger = 'publish'): void
    {
        $urls = array_values(array_filter(array_unique($urls)));

        if ($urls === [] || ! $this->enabled()) {
            return;
        }

        $key         = (string) config('search.indexnow_key');
        $siteUrl     = rtrim((string) config('search.site_url'), '/');
        $host        = parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl;
        $keyLocation = $siteUrl . '/indexnow.txt';

        foreach (array_chunk($urls, self::CHUNK) as $chunk) {
            $status = null;

            try {
                $status = Http::timeout(3)->post(self::ENDPOINT, [
                    'host'        => $host,
                    'key'         => $key,
                    'keyLocation' => $keyLocation,
                    'urlList'     => array_values($chunk),
                ])->status();
            } catch (\Throwable $e) {
                Log::warning('IndexNow submit failed', ['error' => $e->getMessage()]);
            }

            foreach ($chunk as $url) {
                $this->log($url, $status, $trigger);
            }
        }
    }

    private function enabled(): bool
    {
        return (bool) config('search.ping_enabled') && (string) config('search.indexnow_key') !== '';
    }

    private function log(string $url, ?int $status, string $trigger): void
    {
        try {
            SearchSubmission::create([
                'url'           => mb_substr($url, 0, 500),
                'engine'        => 'indexnow',
                'trigger'       => $trigger,
                'response_code' => $status,
                'submitted_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Logging is best-effort; never let it break the caller.
        }
    }
}
