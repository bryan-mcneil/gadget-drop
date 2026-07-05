<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SearchSiteDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        return Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Test Widget Review',
            'slug'         => 'test-widget-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    /** Configure Google and pre-seed the bearer token so google/auth never runs. */
    private function configureGoogle(): void
    {
        config(['services.google_search_console.property' => 'sc-domain:gadgetdrop.tech']);
        Cache::put('gsc.token', 'fake-token', now()->addHour());
    }

    private function fakeGoogle(): void
    {
        $today = now()->toDateString();
        $post  = url('/posts/test-widget-review');
        $home  = url('/');

        Http::fake(function ($request) use ($today, $post, $home) {
            if (! str_contains($request->url(), 'searchconsole.googleapis.com')) {
                return Http::response([], 200);
            }

            $dims = $request->data()['dimensions'] ?? [];
            $type = $request->data()['type'] ?? 'web';

            // Discover / News return nothing in this fixture.
            if ($type !== 'web') {
                return Http::response(['rows' => []]);
            }

            $rows = match ($dims) {
                ['date'] => [
                    ['keys' => [$today], 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05, 'position' => 8.4],
                ],
                ['date', 'page'] => [
                    ['keys' => [$today, $post], 'clicks' => 5, 'impressions' => 120, 'position' => 6.1],
                    ['keys' => [$today, $home], 'clicks' => 2, 'impressions' => 40, 'position' => 15.0],
                ],
                ['date', 'query', 'page'] => [
                    ['keys' => [$today, 'best widget', $post], 'clicks' => 4, 'impressions' => 90, 'position' => 5.5],
                    ['keys' => [$today, 'widget review', $post], 'clicks' => 1, 'impressions' => 30, 'position' => 12.0],
                ],
                default => [],
            };

            return Http::response(['rows' => $rows]);
        });
    }

    public function test_no_configured_engine_is_an_explicit_no_op(): void
    {
        Http::fake();

        $this->artisan('search:sync')
            ->expectsOutputToContain('Google Search Console not configured')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_google_sync_upserts_all_grains_and_resolves_post_id(): void
    {
        $post = $this->makePost();
        $this->configureGoogle();
        $this->fakeGoogle();

        $this->artisan('search:sync --days=2')->assertSuccessful();

        $this->assertDatabaseHas('search_site_days', [
            'source' => 'google', 'search_type' => 'web', 'clicks' => 10, 'impressions' => 200,
        ]);
        $this->assertDatabaseHas('search_page_days', [
            'url_hash' => sha1(url('/posts/test-widget-review')), 'post_id' => $post->id, 'clicks' => 5,
        ]);
        // The home page is not a post → post_id stays null.
        $this->assertDatabaseHas('search_page_days', [
            'url_hash' => sha1(url('/')), 'post_id' => null,
        ]);
        $this->assertDatabaseHas('search_query_days', [
            'query_hash' => sha1('best widget'), 'clicks' => 4, 'impressions' => 90,
        ]);
        $this->assertSame(2, \App\Models\SearchQueryDay::count());
    }

    public function test_re_running_restates_rather_than_duplicates(): void
    {
        $this->makePost();
        $this->configureGoogle();
        $this->fakeGoogle();

        $this->artisan('search:sync --days=2')->assertSuccessful();
        $counts = [
            SearchSiteDay::count(),
            \App\Models\SearchPageDay::count(),
            \App\Models\SearchQueryDay::count(),
        ];

        $this->artisan('search:sync --days=2')->assertSuccessful();

        $this->assertSame($counts, [
            SearchSiteDay::count(),
            \App\Models\SearchPageDay::count(),
            \App\Models\SearchQueryDay::count(),
        ]);
    }

    public function test_bing_sync_records_site_days_and_weekly_query_snapshot(): void
    {
        // Travel to a Sunday so the weekly query snapshot also runs.
        $this->travelTo(now()->next(Carbon::SUNDAY));

        config(['services.bing_webmaster.api_key' => 'bing-key']);
        $ms = now()->startOfDay()->getTimestamp() * 1000;

        Http::fake([
            '*GetRankAndTrafficStats*' => Http::response(['d' => [
                ['Date' => "/Date({$ms})/", 'Clicks' => 3, 'Impressions' => 50, 'AvgImpressionPosition' => 9.0],
            ]]),
            '*GetQueryStats*' => Http::response(['d' => [
                ['Query' => 'bing widget', 'Clicks' => 2, 'Impressions' => 33, 'AvgClickPosition' => 3.1, 'AvgImpressionPosition' => 7.7],
            ]]),
        ]);

        $this->artisan('search:sync')->assertSuccessful();

        $this->assertDatabaseHas('search_site_days', [
            'source' => 'bing', 'search_type' => 'web', 'clicks' => 3, 'impressions' => 50,
        ]);
        $this->assertDatabaseHas('bing_query_stats', [
            'query_hash' => sha1('bing widget'), 'clicks' => 2, 'impressions' => 33,
        ]);
    }

    public function test_prune_drops_rows_older_than_retention(): void
    {
        Http::fake();

        SearchSiteDay::create([
            'source' => 'google', 'date' => now()->subMonths(20)->toDateString(),
            'search_type' => 'web', 'clicks' => 1, 'impressions' => 1,
        ]);
        SearchSiteDay::create([
            'source' => 'google', 'date' => now()->toDateString(),
            'search_type' => 'web', 'clicks' => 1, 'impressions' => 1,
        ]);

        $this->artisan('search:sync')->assertSuccessful();

        $this->assertSame(1, SearchSiteDay::count());
        $this->assertDatabaseMissing('search_site_days', ['date' => now()->subMonths(20)->toDateString()]);
    }
}
