<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookTokenCommandTest extends TestCase
{
    public function test_the_ritual_exchanges_and_prints_the_env_block(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'long-lived-token']),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [['name' => 'GadgetDrop', 'id' => '111222333', 'access_token' => 'page-token-forever']],
            ]),
        ]);

        $this->artisan('social:fb-token --app-id=123 --app-secret=shhh --token=short-lived')
            ->expectsOutputToContain('FACEBOOK_PAGE_ID=111222333')
            ->expectsOutputToContain('FACEBOOK_PAGE_TOKEN=page-token-forever')
            ->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/access_token')
            && str_contains($r->url(), 'fb_exchange_token=short-lived'));
    }

    public function test_a_failed_exchange_explains_the_expiry_gotcha(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['error' => ['message' => 'Session has expired']], 400),
        ]);

        $this->artisan('social:fb-token --app-id=123 --app-secret=shhh --token=stale')
            ->expectsOutputToContain('Session has expired')
            ->expectsOutputToContain('generate a fresh one')
            ->assertFailed();
    }

    public function test_no_pages_is_a_clear_failure_not_a_blank_success(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'long-lived-token']),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => []]),
        ]);

        $this->artisan('social:fb-token --app-id=123 --app-secret=shhh --token=short-lived')
            ->expectsOutputToContain('No Pages returned')
            ->assertFailed();
    }
}
