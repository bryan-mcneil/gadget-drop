<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\ReleaseCycle;
use App\Models\User;
use App\Support\BuyOrWait;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Plan 06 §Phase 6.3: the public buy-or-wait surface. The affiliate assertion
 * is the load-bearing one: these are editorial-integrity pages, so the single
 * CTA stays on the review and NO /out/ link appears here at all.
 */
class BuyOrWaitPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Verdicts, the index rows and PriceIntel all share the array store.
        Cache::flush();
    }

    private function cycle(array $overrides = []): ReleaseCycle
    {
        return ReleaseCycle::create(array_merge([
            'name' => 'Test Line',
            'slug' => 'test-line',
            'typical_month' => 9,
            'cadence_months' => 12,
            'last_release_name' => 'Test Line 3',
            'last_release_at' => now()->subMonths(11)->toDateString(),   // late cycle
            'next_expected_note' => 'Shipped every September since 2019.',
            'source_url' => 'https://example.com/press-release',
            'verified_at' => now()->subMonth()->toDateString(),
        ], $overrides));
    }

    /**
     * A tracked, reviewed product on a line: held at $historic for 60 days,
     * currently at $current. Seeding mirrors DealsPageTest.
     */
    private function trackedProduct(string $name, float $historic, float $current, ?Category $category = null): Product
    {
        static $i = 0;
        $i++;

        $category ??= Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'asin' => "B00BOW{$i}000",
            'affiliate_url' => 'https://www.amazon.com/dp/B00BOW',
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
        $post->categories()->attach($category->id);

        return $product;
    }

    // ------------------------------------------------------------------ index

    public function test_the_index_lists_lines_grouped_by_verdict(): void
    {
        $this->cycle(['slug' => 'late-line', 'name' => 'Late Line', 'last_release_at' => now()->subMonths(11)->toDateString()]);
        $this->cycle(['slug' => 'fresh-line', 'name' => 'Fresh Line', 'last_release_at' => now()->subMonths(2)->toDateString()]);

        $this->get('/buy-or-wait')
            ->assertOk()
            ->assertSee('Is now a good time to buy?', false)
            ->assertSee('Late Line', false)
            ->assertSee('Fresh Line', false)
            ->assertSee('A refresh is close', false)
            ->assertSee('Wait for the refresh', false)
            ->assertSee('cycle data verified', false);
    }

    public function test_an_empty_index_is_not_published_as_a_thin_page(): void
    {
        $this->get('/buy-or-wait')->assertNotFound();
    }

    public function test_the_index_is_indexable_with_a_canonical(): void
    {
        $this->cycle();

        $this->get('/buy-or-wait')
            ->assertOk()
            ->assertDontSee('name="robots" content="noindex', false)
            ->assertSee('rel="canonical" href="'.route('buy-or-wait.index').'"', false);
    }

    // ------------------------------------------------------------------- show

    public function test_a_cycle_page_renders_the_dated_verdict_hero_and_factors(): void
    {
        $this->cycle(['slug' => 'iphone', 'name' => 'iPhone', 'last_release_name' => 'iPhone 17']);

        $response = $this->get('/buy-or-wait/iphone');

        $response->assertOk()
            ->assertSee('Should you buy the iPhone now or wait? ('.now()->format('F Y').')', false)
            ->assertSee('Wait for the refresh', false)
            ->assertSee('as of '.now()->format('F j, Y'), false)
            ->assertSee('What this is based on', false)
            ->assertSee('Release cadence', false)
            ->assertSee('Current model', false)
            ->assertSee('Historically the iPhone line refreshes', false)
            ->assertSee('https://example.com/press-release', false)
            ->assertSee('Shipped every September since 2019.', false);
    }

    public function test_a_cycle_page_states_when_its_data_was_last_verified(): void
    {
        $cycle = $this->cycle();

        $this->get('/buy-or-wait/test-line')
            ->assertOk()
            ->assertSee('last verified against its source on', false)
            ->assertSee($cycle->verified_at->format('F j, Y'), false);
    }

    public function test_stale_cycle_data_is_flagged_on_the_page(): void
    {
        $this->cycle(['verified_at' => now()->subMonths(9)->toDateString()]);

        $this->get('/buy-or-wait/test-line')
            ->assertOk()
            ->assertSee('Heads up:', false)
            ->assertSee('directional until we refresh it', false)
            ->assertDontSee('high confidence', false);
    }

    public function test_an_unknown_line_404s(): void
    {
        $this->cycle();

        $this->get('/buy-or-wait/not-a-real-line')->assertNotFound();
    }

    public function test_the_page_emits_faq_json_ld_with_the_verdict_as_the_answer(): void
    {
        $this->cycle(['slug' => 'iphone', 'name' => 'iPhone']);

        $this->get('/buy-or-wait/iphone')
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('Should I buy the iPhone now or wait?', false)
            ->assertSee('When is the next iPhone expected?', false)
            ->assertSee('We do not publish predictions for unannounced products', false);
    }

    public function test_the_price_block_renders_for_a_line_with_a_tracked_review(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->cycle([
            'slug' => 'airpods-pro',
            'name' => 'AirPods Pro',
            'category_id' => $audio->id,
            'last_release_at' => now()->subMonths(10)->toDateString(),
            'cadence_months' => 36,
        ]);
        $this->trackedProduct('AirPods Pro 3', 249, 199, $audio);

        $this->get('/buy-or-wait/airpods-pro')
            ->assertOk()
            ->assertSee('What AirPods Pro 3 costs right now', false)
            ->assertSee('Tracked price', false)
            ->assertSee('Read our AirPods Pro 3 Review', false)
            ->assertSee('Buy now', false);
    }

    public function test_a_line_without_tracked_price_history_says_so_instead_of_guessing(): void
    {
        $this->cycle(['slug' => 'iphone', 'name' => 'iPhone']);

        $this->get('/buy-or-wait/iphone')
            ->assertOk()
            ->assertSee('Price context', false)
            ->assertSee('rests on release timing alone', false)
            // The factor still appears and names the gate; silence would read
            // as "no price problem" rather than "no price data".
            ->assertSee('Not weighed in', false)
            ->assertSee('snapshots spanning', false)
            // ...but the price widget itself does not render.
            ->assertDontSee('costs right now', false)
            ->assertDontSee('Confirm the final price at checkout', false);
    }

    // ------------------------------------------------------ integrity + wiring

    public function test_buy_or_wait_pages_carry_no_affiliate_links(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->cycle(['slug' => 'airpods-pro', 'name' => 'AirPods Pro', 'category_id' => $audio->id]);
        $this->trackedProduct('AirPods Pro 3', 249, 199, $audio);

        // The single CTA rule: these are editorial pages, the review holds the link.
        $this->get('/buy-or-wait')->assertOk()->assertDontSee('/out/', false);
        $this->get('/buy-or-wait/airpods-pro')
            ->assertOk()
            ->assertDontSee('/out/', false)
            ->assertDontSee('amazon.com', false);
    }

    public function test_buy_or_wait_pages_are_in_the_sitemap(): void
    {
        $this->cycle(['slug' => 'iphone', 'name' => 'iPhone']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('buy-or-wait.index'), false)
            ->assertSee(route('buy-or-wait.show', 'iphone'), false);
    }

    public function test_the_sitemap_omits_the_index_while_no_lines_exist(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee(route('buy-or-wait.index').'<', false);
    }

    public function test_the_methodology_anchor_the_pages_link_to_exists(): void
    {
        $this->cycle();

        $this->get('/how-we-review')
            ->assertOk()
            ->assertSee('id="buy-or-wait"', false)
            ->assertSee('How our buy-or-wait verdicts work', false)
            ->assertSee('What we <strong>don\'t</strong> do is rumours', false);
    }

    public function test_the_footer_and_deals_page_link_into_the_feature(): void
    {
        $this->cycle();

        $this->get('/deals')
            ->assertOk()
            ->assertSee(route('buy-or-wait.index'), false)
            ->assertSee('buy-or-wait verdicts', false);
    }

    public function test_index_rows_carry_confidence_so_a_thin_verdict_cannot_look_certain(): void
    {
        $this->cycle(['slug' => 'iphone', 'name' => 'iPhone']);

        // No tracked price on this line -> cycle-only -> medium at best.
        $this->get('/buy-or-wait')
            ->assertOk()
            ->assertSee('Medium confidence', false)
            ->assertDontSee('High confidence', false);
    }

    public function test_verdict_labels_stay_in_sync_between_engine_and_page(): void
    {
        $this->cycle(['slug' => 'iphone', 'name' => 'iPhone']);

        $this->get('/buy-or-wait/iphone')
            ->assertOk()
            ->assertSee(BuyOrWait::label(BuyOrWait::WAIT_FOR_REFRESH), false);
    }
}
