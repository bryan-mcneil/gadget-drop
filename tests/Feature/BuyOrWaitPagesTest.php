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

    /**
     * The August 2026 prod incident: /buy-or-wait was hit while the cycle table
     * was still empty, the six-hour index cache stored that empty array, and the
     * page kept 404ing for hours after the cycles were seeded. Nothing an
     * operator reaches for fixes that (a CDN purge is the wrong layer; the
     * seeder does not touch the cache), so the 404 state must never be stored.
     */
    public function test_seeding_a_line_clears_the_empty_index_404_at_once(): void
    {
        $this->get('/buy-or-wait')->assertNotFound();

        $this->cycle(['slug' => 'newly-seeded', 'name' => 'Newly Seeded']);

        $this->get('/buy-or-wait')
            ->assertOk()
            ->assertSee('Newly Seeded', false);
    }

    /** The same staleness in the other direction: a warm index must see a new line. */
    public function test_a_new_line_appears_on_an_already_cached_index(): void
    {
        $this->cycle(['slug' => 'first-line', 'name' => 'First Line']);

        $this->get('/buy-or-wait')
            ->assertOk()
            ->assertDontSee('Second Line', false);

        $this->cycle(['slug' => 'second-line', 'name' => 'Second Line']);

        $this->get('/buy-or-wait')
            ->assertOk()
            ->assertSee('Second Line', false);
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

    /**
     * Retro-review finding 3: metaDescription() formatted 'has been out %d.'
     * with no unit word, shipping "iPhone 17 has been out 11." as the SERP
     * snippet of every cycle page. Nothing asserted the tag, which is why it
     * shipped and then sat live for a month.
     */
    public function test_the_meta_description_states_the_model_age_with_its_unit(): void
    {
        $this->cycle(['slug' => 'unit-line', 'name' => 'Unit Line', 'last_release_at' => now()->subMonths(11)->toDateString()]);

        $description = $this->metaDescriptionOf('/buy-or-wait/unit-line');

        $this->assertStringContainsString('has been out 11 months', $description);
        $this->assertStringNotContainsString('has been out 11.', $description);
    }

    /** The two ages a bare count renders as a bug: one month, and younger than one. */
    public function test_the_meta_description_handles_one_month_and_a_just_launched_model(): void
    {
        $this->cycle(['slug' => 'one-month', 'name' => 'One Month Line', 'last_release_at' => now()->subMonth()->toDateString()]);
        $this->cycle(['slug' => 'brand-new', 'name' => 'Brand New Line', 'last_release_at' => now()->subDays(5)->toDateString()]);

        $this->assertStringContainsString('has been out 1 month.', $this->metaDescriptionOf('/buy-or-wait/one-month'));
        $this->assertStringContainsString('is less than a month old', $this->metaDescriptionOf('/buy-or-wait/brand-new'));
    }

    /**
     * CONTENT-GUIDELINES.md budgets a meta description at 120-155 characters.
     * Worst case in the shipped dataset: the longest line name, a late cycle
     * (longest verdict label) and a two-digit age. The old template missed the
     * budget on all ten rows, at 166 to 196 characters.
     */
    public function test_the_meta_description_fits_the_sites_snippet_budget(): void
    {
        $this->cycle([
            'slug' => 'kindle-paperwhite',
            'name' => 'Kindle Paperwhite',
            'cadence_months' => 36,
            'last_release_at' => now()->subMonths(34)->toDateString(),
        ]);

        $description = $this->metaDescriptionOf('/buy-or-wait/kindle-paperwhite');

        $this->assertGreaterThanOrEqual(120, mb_strlen($description));
        $this->assertLessThanOrEqual(155, mb_strlen($description));
    }

    /** The rendered <meta name="description"> content, decoded. */
    private function metaDescriptionOf(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]+">/', $html);

        preg_match('/<meta name="description" content="([^"]*)">/', $html, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES);
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
