<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Models\WorthItVote;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorthItVoteSurfacesTest extends TestCase
{
    use RefreshDatabase;

    private function publishedReview(string $name): Post
    {
        return Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "{$name} Review",
            'slug' => str_replace(' ', '-', strtolower($name)).'-'.uniqid(),
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    /** A qualifying /deals drop (held high for weeks, now lower) linked to $post. */
    private function trackedDrop(Post $post, string $name, float $historic, float $current): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'asin' => "B00WVOTE{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00WVOTE',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $historic,
        ]);

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $historic,
            'source' => 'manual',
            'created_at' => now()->subDays(60),
        ]);

        if ($current !== $historic) {
            $product->update(['price' => $current]);
        }

        PriceIntel::flush($product->id);

        $post->products()->attach($product->id, ['display_order' => 1]);

        return $product;
    }

    public function test_the_post_page_mounts_the_vote_component(): void
    {
        $post = $this->publishedReview('Mountable Widget');

        // The prompt text is unique to the component — seeing it proves the
        // @livewire('worth-it-vote') name resolved and the component rendered.
        $this->get('/posts/'.$post->slug)
            ->assertOk()
            ->assertSee('Was this worth it?', false)
            ->assertSee('Worth it', false)
            ->assertSee("I'd skip", false);
    }

    public function test_deals_shows_the_worth_percentage_once_the_gate_is_cleared(): void
    {
        Cache::flush();
        $post = $this->publishedReview('Loved Widget');
        $this->trackedDrop($post, 'Loved Widget', 100, 80);

        // 5 worth + 1 skip = 6 total → gate cleared → round(5/6) = 83%.
        WorthItVote::factory()->count(5)->worth()->create(['post_id' => $post->id]);
        WorthItVote::factory()->skip()->create(['post_id' => $post->id]);
        Cache::forget('deals.feed');

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Loved Widget', false)
            ->assertSee('83% of 6 readers say worth it', false);
    }

    public function test_deals_hides_the_worth_line_below_the_gate(): void
    {
        Cache::flush();
        $post = $this->publishedReview('Barely Voted Widget');
        $this->trackedDrop($post, 'Barely Voted Widget', 100, 80);

        // Only 3 votes — under the 5-vote gate.
        WorthItVote::factory()->count(3)->worth()->create(['post_id' => $post->id]);
        Cache::forget('deals.feed');

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Barely Voted Widget', false)   // the deal still qualifies on price
            ->assertDontSee('readers say worth it', false);
    }

    public function test_deals_vote_counting_does_not_add_per_post_queries(): void
    {
        Cache::flush();

        // Ten qualifying deals, each with votes. The tallies must ride the single
        // posts eager-load (withCount), not a per-post COUNT pair: a regression to
        // $post->worthItSummary() would add ~2 queries PER POST (~20 here), which
        // the ceiling below is set to catch with margin.
        $postCount = 10;
        for ($n = 1; $n <= $postCount; $n++) {
            $post = $this->publishedReview("Feed Widget {$n}");
            $this->trackedDrop($post, "Feed Widget {$n}", 100, 80);
            WorthItVote::factory()->count(8)->worth()->create(['post_id' => $post->id]);
            WorthItVote::factory()->count(2)->skip()->create(['post_id' => $post->id]);
        }

        Cache::flush();
        DB::enableQueryLog();

        $this->get('/deals')->assertOk()->assertSee('readers say worth it', false);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Correct (folded) implementation is ~27 queries for 10 posts; the +2/post
        // regression would land near 47. 35 sits between — clears the real count
        // with headroom, breaches on a reintroduced per-post COUNT pair.
        $this->assertLessThanOrEqual(35, $queries, "deals query count grew unexpectedly: {$queries}");
    }

    public function test_deals_carries_no_vote_ui_only_read_only_social_proof(): void
    {
        Cache::flush();
        $post = $this->publishedReview('Read Only Widget');
        $this->trackedDrop($post, 'Read Only Widget', 100, 80);
        WorthItVote::factory()->count(5)->worth()->create(['post_id' => $post->id]);
        WorthItVote::factory()->skip()->create(['post_id' => $post->id]);
        Cache::forget('deals.feed');

        $this->get('/deals')
            ->assertOk()
            ->assertSee('readers say worth it', false)   // the read-only line is present
            ->assertDontSee('Was this worth it?', false) // ...but no ballot
            ->assertDontSee('wire:click', false);        // ...and nothing to click
    }

    public function test_deals_survives_a_pre_deploy_cached_feed_missing_the_vote_keys(): void
    {
        // Simulate the 1h window after deploy where deals.feed was cached with the
        // OLD array shape (no worth_pct/worth_total). The ?? guard must render it.
        Cache::put('deals.feed', [[
            'product_id' => 1, 'name' => 'Stale Cached Widget', 'image_url' => null,
            'current' => 80.0, 'typical' => 100.0, 'low90' => 78.0, 'drop_pct' => 20.0,
            'verdict' => 'good', 'checked_at' => now()->toIso8601String(), 'points' => [],
            'post_id' => 1, 'post_title' => 'Stale', 'post_slug' => 'stale-cached-widget',
            // deliberately no 'worth_pct' / 'worth_total'
        ]], now()->addHour());

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Stale Cached Widget', false)
            ->assertDontSee('readers say worth it', false);
    }
}
