<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchInspectCommandTest extends TestCase
{
    use RefreshDatabase;

    private function publish(int $daysAgo = 1): Post
    {
        return Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Inspect Widget Review',
            'slug'         => 'inspect-widget-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDays($daysAgo),
        ]);
    }

    private function configureGoogle(): void
    {
        config(['services.google_search_console.property' => 'sc-domain:gadgetdrop.tech']);
        Cache::put('gsc.token', 'fake-token', now()->addHour());
    }

    public function test_no_op_without_credentials(): void
    {
        Http::fake();
        $this->publish();

        $this->artisan('search:inspect')
            ->expectsOutputToContain('not configured')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_inspects_and_stores_verdict(): void
    {
        $post = $this->publish();
        $this->configureGoogle();

        Http::fake([
            '*urlInspection*' => Http::response(['inspectionResult' => ['indexStatusResult' => [
                'verdict'       => 'PASS',
                'coverageState' => 'Submitted and indexed',
                'lastCrawlTime' => now()->toIso8601String(),
            ]]]),
        ]);

        $this->artisan('search:inspect')
            ->expectsOutputToContain('Inspected 1, indexed 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('search_index_checks', [
            'post_id' => $post->id,
            'verdict' => 'PASS',
        ]);
    }

    public function test_escalates_a_post_still_unindexed_past_the_window(): void
    {
        $post = $this->publish(6); // older than the 4-day escalation window
        $this->configureGoogle();
        config(['search.ping_enabled' => true, 'search.indexnow_key' => 'abc123']);

        Http::fake([
            '*urlInspection*'   => Http::response(['inspectionResult' => ['indexStatusResult' => [
                'verdict'       => 'NEUTRAL',
                'coverageState' => 'Crawled - currently not indexed',
            ]]]),
            '*sitemaps/*'       => Http::response('', 200),
            'api.indexnow.org/*' => Http::response('', 200),
        ]);

        $this->artisan('search:inspect')
            ->expectsOutputToContain('escalated 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('search_index_checks', ['post_id' => $post->id, 'verdict' => 'NEUTRAL']);
        $this->assertDatabaseHas('search_submissions', ['engine' => 'indexnow', 'trigger' => 'retry']);
        $this->assertDatabaseHas('search_submissions', ['engine' => 'google_sitemap', 'trigger' => 'retry']);
    }
}
