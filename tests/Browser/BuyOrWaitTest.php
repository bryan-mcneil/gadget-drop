<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\ReleaseCycle;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser truth for the buy-or-wait surface (plan 06 §Phase 6.6): the index
 * renders grouped verdict chips, a cycle page renders the dated hero plus the
 * sourced factor list, and the review-page strip links the two together.
 *
 * Local run (two terminals from the project root): `php artisan serve` in one,
 * `php artisan dusk` in the other. Uses the sqlite FILE database
 * (database/dusk.sqlite) + DatabaseTruncation; never MySQL.
 *
 * The dusk cache store is `file` and DatabaseTruncation does NOT clear it, so
 * seeding ends with PriceIntel::flush() (busts the per-product stats key across
 * both processes). The buy-or-wait caches are keyed by the cycle row's
 * updated_at plus the calendar day, so a freshly created cycle is always a
 * fresh key and needs no equivalent flush.
 */
class BuyOrWaitTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function cycle(array $overrides = []): ReleaseCycle
    {
        return ReleaseCycle::create(array_merge([
            'name' => 'Dusk Phone',
            'slug' => 'dusk-phone',
            'typical_month' => 9,
            'cadence_months' => 12,
            'last_release_name' => 'Dusk Phone 17',
            'last_release_at' => now()->subMonths(11)->toDateString(),
            'next_expected_note' => 'Announced every September since 2012.',
            'source_url' => 'https://example.com/dusk-press-release',
            'verified_at' => now()->subMonth()->toDateString(),
        ], $overrides));
    }

    public function test_the_index_groups_lines_under_verdict_headings(): void
    {
        $this->cycle();
        $this->cycle([
            'name' => 'Dusk Buds',
            'slug' => 'dusk-buds',
            'last_release_name' => 'Dusk Buds 3',
            'cadence_months' => 36,
            'last_release_at' => now()->subMonths(4)->toDateString(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/buy-or-wait')
                ->assertSee('Is now a good time to buy?')
                ->assertSee('A refresh is close')
                ->assertSee('Dusk Phone')
                ->assertSee('Wait for the refresh')
                ->assertSee('Dusk Buds')
                ->assertSee('cycle data verified');
        });
    }

    public function test_a_cycle_page_shows_the_dated_hero_and_its_sourced_factors(): void
    {
        $this->cycle();

        $this->browse(function (Browser $browser) {
            $browser->visit('/buy-or-wait/dusk-phone')
                ->assertSee('Should you buy the Dusk Phone now or wait? ('.now()->format('F Y').')')
                ->assertSee('Wait for the refresh')
                ->assertSee('as of '.now()->format('F j, Y'))
                ->assertSee('What this is based on')
                // Factor labels carry Tailwind's `uppercase`, and Selenium
                // returns text as RENDERED, so assert the transformed casing.
                ->assertSee('RELEASE CADENCE')
                ->assertSee('CURRENT MODEL')
                ->assertSee('Announced every September since 2012.')
                ->assertSeeLink('source')
                ->assertSee('last verified against its source on');
        });
    }

    public function test_the_review_strip_navigates_to_the_cycle_page(): void
    {
        $this->cycle(['name' => 'Dusk Buds', 'slug' => 'dusk-buds', 'cadence_months' => 36]);

        $category = Category::firstOrCreate(['slug' => 'audio'], ['name' => 'Audio']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Dusk Buds 3',
            'brand' => 'Dusk',
            'asin' => 'B00DUSKBW1',
            'affiliate_url' => 'https://www.amazon.com/dp/B00DUSKBW1',
            'price' => 249,
        ]);

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => 249,
            'source' => 'manual',
            'created_at' => now()->subDays(60),
        ]);
        $product->update(['price' => 199]);
        PriceIntel::flush($product->id);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Dusk Buds 3 Review',
            'slug' => 'dusk-buds-3-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/posts/dusk-buds-3-review')
                ->assertSee('Buy or wait?')
                ->assertSee('On the Dusk Buds line')
                ->clickLink('See the timing →')
                ->waitForLocation('/buy-or-wait/dusk-buds')
                ->assertSee('Should you buy the Dusk Buds now or wait?');
        });
    }
}
