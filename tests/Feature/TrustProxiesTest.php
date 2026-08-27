<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * request()->ip() is the key for every per-reader guard on this site: the
 * login throttle, the contact / newsletter / watch-signup throttles, the
 * Worth It vote dedupe, and the per-IP limits on both Claude features.
 * Behind Hostinger's CDN that address can be the edge rather than the
 * reader, which would collapse all of them into one site-wide bucket.
 *
 * These tests pin the two ends of that: what happens with nothing trusted
 * (the safe default) and what happens once the calling proxy is trusted --
 * including the property that actually makes trusting it safe, namely that
 * a reader cannot forge the header to impersonate someone else.
 *
 * @see config/trustedproxy.php
 * @see docs/plans/11-live-price-compare.md Deployment step 5a
 */
class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const CDN_EDGE = '198.51.100.10';

    private const READER = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__proxy-probe', fn () => response()->json([
            'ip' => request()->ip(),
            'host' => request()->getHost(),
        ]));
    }

    /**
     * @param  array<string, string>  $server
     */
    private function probe(array $server = []): TestResponse
    {
        return $this->call('GET', '/__proxy-probe', server: array_merge([
            'REMOTE_ADDR' => self::CDN_EDGE,
        ], $server));
    }

    public function test_a_forwarded_header_is_ignored_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->probe(['HTTP_X_FORWARDED_FOR' => self::READER])
            ->assertJsonPath('ip', self::CDN_EDGE);
    }

    public function test_trusting_the_calling_proxy_reveals_the_real_reader(): void
    {
        config(['trustedproxy.proxies' => 'REMOTE_ADDR']);

        $this->probe(['HTTP_X_FORWARDED_FOR' => self::READER])
            ->assertJsonPath('ip', self::READER);
    }

    /**
     * The whole reason trusting the edge is safe. A reader who sends their
     * own X-Forwarded-For gets it PREPENDED to the chain by the proxy, and
     * Symfony walks the chain from the right, stopping at the first entry
     * that is not itself a trusted proxy. If this ever inverts, every per-IP
     * throttle on the site — including the login throttle — becomes
     * bypassable by rotating a header.
     */
    public function test_a_forged_forwarded_entry_cannot_impersonate_another_reader(): void
    {
        config(['trustedproxy.proxies' => 'REMOTE_ADDR']);

        $this->probe(['HTTP_X_FORWARDED_FOR' => '1.2.3.4, '.self::READER])
            ->assertJsonPath('ip', self::READER);
    }

    /**
     * Laravel's '*' is not "trust every hop" — it resolves to
     * setTrustedProxies([REMOTE_ADDR]), the same single entry as the literal
     * 'REMOTE_ADDR'. Pinned because the older fideloper-package meaning is
     * what most search results still describe, and someone acting on that
     * would believe they had opened a hole they had not.
     */
    public function test_the_wildcard_trusts_only_the_calling_proxy(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->probe(['HTTP_X_FORWARDED_FOR' => '1.2.3.4, '.self::READER])
            ->assertJsonPath('ip', self::READER);
    }

    public function test_an_unrelated_proxy_address_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '192.0.2.55']);

        $this->probe(['HTTP_X_FORWARDED_FOR' => self::READER])
            ->assertJsonPath('ip', self::CDN_EDGE);
    }

    /**
     * url() and route() build every canonical and og:url from the request
     * host, so a trusted X-Forwarded-Host would let a caller dictate the URL
     * we hand Google. bootstrap/app.php narrows the trusted-header bitmask to
     * X-Forwarded-For alone; this fails if anyone widens it back to Laravel's
     * default.
     */
    public function test_a_forwarded_host_is_never_trusted(): void
    {
        config(['trustedproxy.proxies' => 'REMOTE_ADDR']);

        $realHost = $this->probe()->json('host');

        $this->probe([
            'HTTP_X_FORWARDED_FOR' => self::READER,
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
        ])->assertJsonPath('host', $realHost);
    }
}
