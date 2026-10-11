<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Browser truth for the three verdict surfaces (plan 01 §Phase 1.5): the
 * review-page card chip, the /deals badge + 30-day low, and the methodology
 * anchor the chips link to. Local run (two terminals from the project root):
 * `php artisan serve` in one, `php artisan dusk` in the other — see
 * tests/Browser/SmokeTest.php for the env-swap explanation. Uses the sqlite
 * FILE database (database/dusk.sqlite) + DatabaseTruncation; never MySQL.
 *
 * The dusk cache store is `file` and DatabaseTruncation does NOT clear it,
 * so seeding always ends with PriceIntel::flush() — it busts the per-product
 * stats key AND the deals.feed key across both processes.
 */
class VerdictSurfacesTest extends DuskTestCase
{
    use DatabaseTruncation;

    /**
     * A published review whose product held $historic for 60 days and sits at
     * $current today. The 60-day span clears PriceIntel's gates (≥2 snapshots
     * spanning ≥14 days) with real relative dates — no time freezing needed.
     */
    private function trackedReview(string $name, float $historic, float $current): Post
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'asin' => "B00DUSK{$i}0",
            'affiliate_url' => 'https://www.amazon.com/dp/B00DUSK',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $historic,
            'description' => 'A widget.',
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

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "{$name} Review",
            'slug' => str_replace(' ', '-', strtolower($name)).'-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return $post;
    }

    public function test_review_page_shows_the_card_chip_and_the_30_day_line(): void
    {
        // 179 → 149 with variation: today's price IS the 90-day low → 'lowest'.
        $post = $this->trackedReview('Dusk Chip Widget', 179, 149);

        $this->browse(function (Browser $browser) use ($post) {
            $browser->visit('/posts/'.$post->slug)
                // The card chip (and the widget badge below it, same label).
                ->assertSee('Lowest tracked price')
                // The card's quiet methodology link — card-only, chip-gated.
                ->assertSee('How we call deals')
                // The widget's Omnibus reference line (Phase 1.1 surface).
                ->assertSee('Lowest price in the last 30 days');
        });
    }

    public function test_deals_page_shows_the_shared_badge_and_30_day_low(): void
    {
        // Dip-and-recover (DealsPageTest's fixture): held 100, dipped to 70 ten
        // days ago, recovered to 80 today. ~17% below the 90-day average
        // (qualifies) but not the record low → 'good', and low30 = 70 is
        // distinct from the current 80, so "$70.00" can only be the 30-day-low
        // span. A plain 100 → 80 would read 'Lowest tracked price' instead
        // (today's price IS the record low).
        $post = $this->trackedReview('Dusk Deal Widget', 100, 80);
        $product = $post->products()->first();
        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => 70,
            'source' => 'manual',
            'created_at' => now()->subDays(10),
        ]);
        PriceIntel::flush($product->id);

        $this->browse(function (Browser $browser) {
            $browser->visit('/deals')
                ->assertSee('Dusk Deal Widget')
                ->assertSee('Below typical price')
                ->assertSee('30-day low $70.00');
        });
    }

    public function test_the_methodology_link_lands_on_the_deal_verdicts_anchor(): void
    {
        $post = $this->trackedReview('Dusk Anchor Widget', 179, 149);

        $this->browse(function (Browser $browser) use ($post) {
            $browser->visit('/posts/'.$post->slug)
                // Plain link (no wire:navigate) by design: native navigation is
                // what reliably scrolls to a #fragment.
                ->clickLink('How we call deals')
                // clickLink is a JS synthetic click that does NOT block for the
                // native navigation it triggers — gate on the location or the
                // asserts below race the page load (reviewer WARN).
                ->waitForLocation('/how-we-review')
                ->assertPathIs('/how-we-review')
                ->assertFragmentIs('deal-verdicts')
                ->assertVisible('#deal-verdicts')
                ->assertSee('The four verdicts');
        });
    }
}
