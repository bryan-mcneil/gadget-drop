<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SearchOpportunity;
use App\Models\SearchQueryDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchMineCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $slug): Post
    {
        return Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDays(30),
        ]);
    }

    private function queryDay(string $query, string $page, int $impr, int $clicks, float $pos, int $daysAgo = 20): void
    {
        SearchQueryDay::create([
            'date' => now()->subDays($daysAgo)->toDateString(),
            'query' => $query,
            'query_hash' => sha1($query),
            'page_url' => $page,
            'url_hash' => sha1($page),
            'clicks' => $clicks,
            'impressions' => $impr,
            'position' => $pos,
        ]);
    }

    public function test_striking_distance_opportunity_for_a_page_one_post(): void
    {
        $post = $this->makePost('sd-widget');
        $this->queryDay('best sd widget', url('/posts/sd-widget'), 50, 1, 8.0);

        $this->artisan('search:mine')->assertSuccessful();

        $this->assertDatabaseHas('search_opportunities', [
            'kind' => 'striking_distance',
            'post_id' => $post->id,
            'status' => 'open',
        ]);
        $this->assertTrue((float) SearchOpportunity::first()->score > 0);
    }

    public function test_content_gap_when_best_result_is_not_a_post(): void
    {
        // Best page is the home page (not a review) → a content gap, no post_id.
        $this->queryDay('cheap gap widget', url('/'), 40, 0, 12.0);

        $this->artisan('search:mine')->assertSuccessful();

        $opp = SearchOpportunity::where('kind', 'content_gap')->first();
        $this->assertNotNull($opp);
        $this->assertNull($opp->post_id);
    }

    public function test_mining_is_idempotent_and_clusters_phrasings(): void
    {
        $this->makePost('sd-widget');
        $url = url('/posts/sd-widget');
        $this->queryDay('best sd widget', $url, 50, 1, 8.0);
        $this->queryDay('sd widget review', $url, 40, 1, 7.0);

        $this->artisan('search:mine')->assertSuccessful();
        $this->artisan('search:mine')->assertSuccessful();

        // Two phrasings, one clustered opportunity — and re-running doesn't dupe it.
        $this->assertSame(1, SearchOpportunity::where('kind', 'striking_distance')->count());
        $opp = SearchOpportunity::where('kind', 'striking_distance')->first();
        $this->assertCount(2, $opp->evidence['phrasings']);
    }

    public function test_a_cleared_condition_auto_resolves_to_done(): void
    {
        $this->makePost('sd-widget');
        $this->queryDay('best sd widget', url('/posts/sd-widget'), 50, 1, 8.0);

        $this->artisan('search:mine')->assertSuccessful();
        $this->assertSame('open', SearchOpportunity::first()->status);

        // Demand disappears from the window → the opportunity resolves.
        SearchQueryDay::query()->delete();
        $this->artisan('search:mine')->assertSuccessful();

        $this->assertSame('done', SearchOpportunity::first()->status);
    }

    public function test_a_phrasing_flip_updates_the_same_row_and_does_not_false_resolve(): void
    {
        $this->makePost('sd-widget');
        $url = url('/posts/sd-widget');
        $this->queryDay('phrasing one', $url, 50, 1, 8.0);
        $this->queryDay('phrasing two', $url, 45, 1, 8.0);

        $this->artisan('search:mine')->assertSuccessful();
        $opp = SearchOpportunity::where('kind', 'striking_distance')->firstOrFail();

        // Impressions flip so the cluster's representative phrasing (and its
        // query_hash) changes — must still resolve to the SAME opportunity row.
        SearchQueryDay::query()->delete();
        $this->queryDay('phrasing one', $url, 40, 1, 8.0);
        $this->queryDay('phrasing two', $url, 55, 1, 8.0);

        $this->artisan('search:mine')->assertSuccessful();

        $this->assertSame(1, SearchOpportunity::where('kind', 'striking_distance')->count());
        $fresh = SearchOpportunity::where('kind', 'striking_distance')->firstOrFail();
        $this->assertSame($opp->id, $fresh->id);                  // same row, not a duplicate
        $this->assertSame('open', $fresh->status);                // not false-resolved to done
        $this->assertSame(sha1('phrasing two'), $fresh->query_hash); // descriptive head refreshed
    }

    public function test_a_dismissal_survives_a_phrasing_flip(): void
    {
        $this->makePost('sd-widget');
        $url = url('/posts/sd-widget');
        $this->queryDay('phrasing one', $url, 50, 1, 8.0);
        $this->queryDay('phrasing two', $url, 45, 1, 8.0);
        $this->artisan('search:mine')->assertSuccessful();

        SearchOpportunity::where('kind', 'striking_distance')->firstOrFail()
            ->update(['status' => 'dismissed', 'last_seen_at' => now()]);

        SearchQueryDay::query()->delete();
        $this->queryDay('phrasing one', $url, 40, 1, 8.0);
        $this->queryDay('phrasing two', $url, 55, 1, 8.0);
        $this->artisan('search:mine')->assertSuccessful();

        $this->assertSame(1, SearchOpportunity::where('kind', 'striking_distance')->count());
        $this->assertSame('dismissed', SearchOpportunity::where('kind', 'striking_distance')->firstOrFail()->status);
    }

    public function test_content_gap_fires_when_our_post_ranks_poorly_despite_a_strong_non_post(): void
    {
        $this->makePost('weak-widget');
        $query = 'obscure widget query';
        // Our post has the most impressions but ranks at 30; the home page ranks
        // well (pos 8) with fewer impressions. The gap must not be masked.
        $this->queryDay($query, url('/posts/weak-widget'), 40, 0, 30.0);
        $this->queryDay($query, url('/'), 10, 1, 8.0);

        $this->artisan('search:mine')->assertSuccessful();

        $this->assertNotNull(SearchOpportunity::where('kind', 'content_gap')->first());
    }

    public function test_a_dismissed_opportunity_is_not_resurfaced(): void
    {
        $this->makePost('sd-widget');
        $this->queryDay('best sd widget', url('/posts/sd-widget'), 50, 1, 8.0);

        $this->artisan('search:mine')->assertSuccessful();
        SearchOpportunity::first()->update(['status' => 'dismissed', 'last_seen_at' => now()]);

        // Same demand still present, but the dismissal window suppresses it.
        $this->artisan('search:mine')->assertSuccessful();

        $this->assertSame('dismissed', SearchOpportunity::first()->status);
    }
}
