<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ReleaseCycle;
use App\Models\User;
use Database\Seeders\ReleaseCycleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Plan 06 §Phase 6.1: the cycle dataset and the math on top of it. The seed
 * assertions are deliberately strict about sourcing: a row without a source_url
 * and a verified_at is exactly the kind of unsourced claim this feature exists
 * to avoid making.
 */
class ReleaseCycleTest extends TestCase
{
    use RefreshDatabase;

    private function cycle(array $overrides = []): ReleaseCycle
    {
        return ReleaseCycle::create(array_merge([
            'name' => 'Test Line',
            'slug' => 'test-line',
            'typical_month' => 9,
            'cadence_months' => 12,
            'last_release_name' => 'Test Line 2',
            'last_release_at' => now()->subMonths(6)->toDateString(),
            'source_url' => 'https://example.com/press-release',
            'verified_at' => now()->toDateString(),
        ], $overrides));
    }

    public function test_cycle_position_is_fresh_mid_and_overdue_across_the_cadence(): void
    {
        Carbon::setTestNow('2026-07-22');

        $fresh = $this->cycle(['slug' => 'fresh', 'last_release_at' => '2026-05-22']);   // 2 of 12 months
        $mid = $this->cycle(['slug' => 'mid', 'last_release_at' => '2025-01-22']);       // 18 of 12 -> overdue
        $late = $this->cycle(['slug' => 'late', 'last_release_at' => '2025-09-22']);     // 10 of 12

        $this->assertEqualsWithDelta(0.167, $fresh->cyclePosition(), 0.01);
        $this->assertEqualsWithDelta(0.833, $late->cyclePosition(), 0.01);
        $this->assertEqualsWithDelta(1.5, $mid->cyclePosition(), 0.01);

        $this->assertTrue($fresh->cyclePosition() < ReleaseCycle::FRESH_CYCLE);
        $this->assertTrue($late->cyclePosition() >= ReleaseCycle::LATE_CYCLE);
        $this->assertTrue($mid->cyclePosition() > 1.0);

        Carbon::setTestNow();
    }

    public function test_a_long_cadence_line_stays_fresh_far_longer(): void
    {
        Carbon::setTestNow('2026-07-22');

        // Ten months into a 36-month line is early; into a 12-month line it is late.
        $threeYear = $this->cycle(['slug' => 'three-year', 'cadence_months' => 36, 'last_release_at' => '2025-09-19']);
        $oneYear = $this->cycle(['slug' => 'one-year', 'cadence_months' => 12, 'last_release_at' => '2025-09-19']);

        $this->assertTrue($threeYear->cyclePosition() < ReleaseCycle::FRESH_CYCLE);
        $this->assertTrue($oneYear->cyclePosition() >= ReleaseCycle::LATE_CYCLE);

        Carbon::setTestNow();
    }

    public function test_months_since_release_never_goes_negative_for_a_future_date(): void
    {
        $future = $this->cycle(['slug' => 'future', 'last_release_at' => now()->addMonth()->toDateString()]);

        $this->assertSame(0.0, $future->monthsSinceRelease());
        $this->assertSame(0.0, $future->cyclePosition());
    }

    public function test_next_expected_at_projects_the_cadence_forward(): void
    {
        $cycle = $this->cycle(['last_release_at' => '2025-09-19', 'cadence_months' => 12]);

        $this->assertSame('2026-09-19', $cycle->nextExpectedAt()->toDateString());
    }

    public function test_stale_scope_and_flag_track_the_verification_date(): void
    {
        $fresh = $this->cycle(['slug' => 'fresh', 'verified_at' => now()->subMonth()->toDateString()]);
        $stale = $this->cycle(['slug' => 'stale', 'verified_at' => now()->subMonths(9)->toDateString()]);

        $this->assertFalse($fresh->isStale());
        $this->assertTrue($stale->isStale());

        $slugs = ReleaseCycle::stale()->pluck('slug')->all();
        $this->assertSame(['stale'], $slugs);
    }

    public function test_stale_scope_groups_its_or_so_extra_filters_hold(): void
    {
        $this->cycle(['slug' => 'stale-audio', 'verified_at' => now()->subMonths(9)->toDateString(), 'typical_month' => 3]);
        $this->cycle(['slug' => 'fresh-audio', 'verified_at' => now()->toDateString(), 'typical_month' => 3]);

        // Without the grouped OR, the fresh row would leak back in.
        $slugs = ReleaseCycle::stale()->where('typical_month', 3)->pluck('slug')->all();

        $this->assertSame(['stale-audio'], $slugs);
    }

    public function test_seeder_is_idempotent_and_every_row_is_sourced(): void
    {
        Category::create(['name' => 'Audio & Home Theater', 'slug' => 'audio']);
        Category::create(['name' => 'Computers & Accessories', 'slug' => 'computers']);
        Category::create(['name' => 'Gaming', 'slug' => 'gaming']);
        Category::create(['name' => 'Cameras', 'slug' => 'cameras']);

        $this->seed(ReleaseCycleSeeder::class);
        $first = ReleaseCycle::count();

        $this->seed(ReleaseCycleSeeder::class);

        $this->assertSame(10, $first);
        $this->assertSame($first, ReleaseCycle::count());

        foreach (ReleaseCycle::all() as $cycle) {
            $this->assertStringStartsWith('https://', $cycle->source_url, "{$cycle->slug} needs a real source URL");
            $this->assertNotNull($cycle->verified_at, "{$cycle->slug} needs a verified_at");
            $this->assertNotNull($cycle->last_release_at);
            $this->assertGreaterThan(0, $cycle->cadence_months);
            $this->assertFalse($cycle->isStale(), "{$cycle->slug} was seeded already stale");
        }

        $this->assertSame('audio', ReleaseCycle::where('slug', 'airpods-pro')->first()->category->slug);
        $this->assertSame('gaming', ReleaseCycle::where('slug', 'nintendo-switch')->first()->category->slug);
    }

    public function test_seeder_survives_a_site_without_the_matching_categories(): void
    {
        $this->seed(ReleaseCycleSeeder::class);

        $this->assertSame(10, ReleaseCycle::count());
        $this->assertNull(ReleaseCycle::where('slug', 'airpods-pro')->first()->category_id);
    }

    public function test_for_product_prefers_a_name_match_over_the_category(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);

        $airpods = $this->cycle(['slug' => 'airpods-pro', 'name' => 'AirPods Pro', 'category_id' => $audio->id]);
        $this->cycle(['slug' => 'sony', 'name' => 'Sony WH-1000X', 'category_id' => $audio->id]);

        $product = Product::create([
            'category_id' => $audio->id,
            'name' => 'AirPods Pro 3',
            'brand' => 'Apple',
            'affiliate_url' => 'https://www.amazon.com/dp/B00AIRPOD',
            'price' => 249,
        ]);

        $this->assertSame($airpods->id, ReleaseCycle::forProduct($product)->id);
    }

    public function test_a_model_number_running_into_the_line_name_still_matches(): void
    {
        $cameras = Category::create(['name' => 'Cameras', 'slug' => 'cameras']);
        $gopro = $this->cycle(['slug' => 'gopro-hero', 'name' => 'GoPro HERO', 'category_id' => $cameras->id]);

        $product = Product::create([
            'category_id' => $cameras->id,
            'name' => 'HERO13 Black',
            'brand' => 'GoPro',
            'affiliate_url' => 'https://www.amazon.com/dp/B00GOPRO',
            'price' => 399,
        ]);

        $this->assertSame($gopro->id, ReleaseCycle::forProduct($product)->id);
    }

    public function test_an_ambiguous_category_refuses_to_guess(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);

        $this->cycle(['slug' => 'ipad', 'name' => 'iPad', 'category_id' => $computers->id]);
        $this->cycle(['slug' => 'macbook-air', 'name' => 'MacBook Air', 'category_id' => $computers->id]);

        $product = Product::create([
            'category_id' => $computers->id,
            'name' => 'Anker 737 Power Bank',
            'brand' => 'Anker',
            'affiliate_url' => 'https://www.amazon.com/dp/B00ANKER',
            'price' => 99,
        ]);

        // Two lines share the hub, so showing either one would be wrong advice.
        $this->assertNull(ReleaseCycle::forProduct($product));
    }

    public function test_a_sole_category_cycle_is_used_as_a_fallback(): void
    {
        $gaming = Category::create(['name' => 'Gaming', 'slug' => 'gaming']);
        $switch = $this->cycle(['slug' => 'nintendo-switch', 'name' => 'Nintendo Switch', 'category_id' => $gaming->id]);

        $product = Product::create([
            'category_id' => $gaming->id,
            'name' => 'Pro Controller',
            'brand' => 'Hori',
            'affiliate_url' => 'https://www.amazon.com/dp/B00HORI',
            'price' => 49,
        ]);

        $this->assertSame($switch->id, ReleaseCycle::forProduct($product)->id);
    }

    public function test_flagship_product_needs_a_published_review_and_a_name_match(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $cycle = $this->cycle(['slug' => 'airpods-pro', 'name' => 'AirPods Pro', 'category_id' => $audio->id]);

        $product = Product::create([
            'category_id' => $audio->id,
            'name' => 'AirPods Pro 3',
            'brand' => 'Apple',
            'affiliate_url' => 'https://www.amazon.com/dp/B00AIRPOD',
            'price' => 249,
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'AirPods Pro 3 Review',
            'slug' => 'airpods-pro-3-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'draft',
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        $this->assertNull($cycle->flagshipProduct(), 'a draft review must not expose a product');

        $post->update(['status' => 'published', 'published_at' => now()->subDay()]);

        $this->assertSame($product->id, $cycle->flagshipProduct()->id);
    }
}
