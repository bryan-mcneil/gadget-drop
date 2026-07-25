<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropPricePuzzle;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function publicRoutes(): array
    {
        return [
            'home' => ['/', 'GadgetDrop'],
            'about' => ['/about', 'What we do'],
            'privacy' => ['/privacy', 'Privacy Policy'],
            'terms' => ['/terms', 'Terms of Service'],
            'contact' => ['/contact', 'Contact GadgetDrop'],
            'cookies' => ['/cookies', 'Cookie Policy'],
            'news' => ['/news', 'Tech News'],
            'search' => ['/search', 'Find your next drop'],
            'tools' => ['/tools', 'Online Tools'],
            'drop-price-archive' => ['/drop-price', 'Drop Price Archive'],
            'unsubscribe' => ['/unsubscribe', 'Unsubscribe'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_public_page_renders_server_side(string $url, string $needle): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($needle, false);
    }

    public static function toolRoutes(): array
    {
        return [
            ['/tools/json-validator', 'JSON Validator'],
            ['/tools/js-css-minifier', 'Minifier'],
            ['/tools/image-editor', 'Image Editor'],
            ['/tools/image-converter', 'Image Converter'],
            ['/tools/background-remover', 'Background Remover'],
            ['/tools/password-generator', 'Password Generator'],
            ['/tools/base64-encoder', 'Base64 Encoder'],
            ['/tools/color-palette', 'Color Palette'],
            ['/tools/meta-tag-previewer', 'Meta Tag Previewer'],
        ];
    }

    #[DataProvider('toolRoutes')]
    public function test_tool_page_renders(string $url, string $needle): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($needle, false);
    }

    public function test_unknown_url_returns_blade_404(): void
    {
        $this->get('/no-such-page-xyz')
            ->assertNotFound()
            ->assertSee('Page not found', false);
    }

    public function test_post_body_renders_framed_inline_image_with_caption(): void
    {
        Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Figure Test Drop',
            'slug' => 'figure-test-drop',
            'type' => 'article',
            'body' => implode("\n\n", [
                'Intro paragraph with enough text to anchor the drop cap and the layout.',
                'Second paragraph so the body has an in-flow slot between paragraphs.',
                'Third paragraph continuing the running text for the placement logic.',
                'Fourth paragraph closing out the article body for this feature test.',
            ]),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'image_1' => '/storage/posts/inline-1.jpg',
            'image_1_fit' => 'contain',
            'image_1_caption' => 'The 616-LED matrix in DIY mode',
        ]);

        $this->get('/posts/figure-test-drop')
            ->assertOk()
            ->assertSee('<figure class="not-prose', false)
            ->assertSee('<figcaption', false)
            ->assertSee('The 616-LED matrix in DIY mode');
    }

    public function test_post_page_renders_exactly_one_h1_with_the_title(): void
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'The One True Headline',
            'slug' => 'one-h1-review',
            'type' => 'article',
            'body' => "Intro paragraph here.\n\n## A Section\n\nMore body text for the reader.",
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $html = $this->get("/posts/{$post->slug}")->assertOk()->getContent();

        // The dark intel band holds the single <h1>; the sidebar TOC and
        // verdict box use lower heading levels.
        $this->assertSame(1, substr_count($html, '<h1'), 'the post page must have exactly one <h1>');
        $this->assertStringContainsString('The One True Headline', $html);
    }

    public function test_intel_strip_summarises_a_tracked_price_on_a_gated_review(): void
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Tracked Widget',
            'asin' => 'B00INTEL0',
            'affiliate_url' => 'https://www.amazon.com/dp/B00INTEL0',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 90,
            'description' => 'A widget worth tracking.',
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Tracked Widget Review',
            'slug' => 'tracked-widget-intel',
            'type' => 'article',
            'body' => "Intro paragraph.\n\nSecond paragraph of the review body.",
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        // Two snapshots spanning > 14 days → PriceIntel's honesty gates open.
        ProductPriceSnapshot::create(['product_id' => $product->id, 'price' => 110, 'source' => 'manual', 'created_at' => now()->subDays(40)]);
        ProductPriceSnapshot::create(['product_id' => $product->id, 'price' => 80, 'source' => 'manual', 'created_at' => now()->subDays(20)]);
        PriceIntel::flush($product->id);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('data-intel-strip', false);
    }

    public function test_intel_strip_is_absent_on_a_tip(): void
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'A Handy Tip',
            'slug' => 'a-handy-tip',
            'type' => 'tech_tip',
            'body' => "Tip body paragraph one.\n\nTip body paragraph two.",
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('data-intel-strip', false);
    }

    public function test_body_h2s_get_scroll_anchor_ids_and_an_on_this_page_nav(): void
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Anchored Review',
            'slug' => 'anchored-review',
            'type' => 'article',
            'body' => implode("\n\n", [
                'Intro paragraph long enough to anchor the layout and the drop cap.',
                '## First Section',
                'First section paragraph with a bit of running text for the reader.',
                '## Second Section',
                'Second section paragraph closing out this short anchored article.',
            ]),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('<h2 id="first-section">', false)
            ->assertSee('<h2 id="second-section">', false)
            ->assertSee('aria-label="On this page"', false)
            ->assertSee('href="#first-section"', false);
    }

    public function test_home_renders_the_drop_price_game_without_leaking_the_price(): void
    {
        // The puzzle product is attached to a published article — the exact pool
        // that Top Picks / Spotlight draw from — so it WOULD surface there with
        // its price unless the controller excludes it. The post also gives the
        // hero band a slide (the game island lives inside it).
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'A Published Drop',
            'slug' => 'a-published-drop',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Secret Mystery Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 4242,
            'description' => 'A test gadget.',
        ]);
        $product->posts()->attach($post->id);

        DropPricePuzzle::create([
            'puzzle_number' => 7,
            'date' => now()->toDateString(),
            'product_id' => $product->id,
            'price' => 4242,
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Guess today\'s price', false)   // the game island rendered
            ->assertSee('Secret Mystery Widget', false)  // product shown openly (no price)
            ->assertDontSee('4242')                      // the snapshot answer, never
            ->assertDontSee('4,242');                    // …nor leaked via Top Picks / Spotlight
    }

    public function test_top_picks_featured_card_shows_the_verdict_chip_once_gates_open(): void
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Featured Gated Widget',
            'asin' => 'B00FEAT01',
            'affiliate_url' => 'https://www.amazon.com/dp/B00FEAT01',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 80,
            'description' => 'A featured widget.',
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Featured Gated Widget Review',
            'slug' => 'featured-gated-widget',
            'type' => 'article',
            'body' => 'Body paragraph.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        // Held at $110 for weeks, now $80 → gates open, current == 90-day low.
        ProductPriceSnapshot::create(['product_id' => $product->id, 'price' => 110, 'source' => 'manual', 'created_at' => now()->subDays(40)]);
        ProductPriceSnapshot::create(['product_id' => $product->id, 'price' => 80, 'source' => 'manual', 'created_at' => now()->subDays(20)]);
        PriceIntel::flush($product->id);

        // The newest article's lead product is the featured Top Pick; its verdict
        // chip renders because the honesty gates are open.
        $this->get('/')
            ->assertOk()
            ->assertSee('Lowest tracked price', false);
    }

    public function test_category_review_cards_show_the_verdict_chip_only_when_gates_open(): void
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $author = User::factory()->create()->id;

        // Gated product: two snapshots spanning > 14 days, current == the low.
        $gated = Product::create([
            'category_id' => $category->id,
            'name' => 'Gated Card Widget',
            'asin' => 'B00CARD01',
            'affiliate_url' => 'https://www.amazon.com/dp/B00CARD01',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 80,
            'description' => 'A gated widget.',
        ]);
        ProductPriceSnapshot::create(['product_id' => $gated->id, 'price' => 110, 'source' => 'manual', 'created_at' => now()->subDays(40)]);
        ProductPriceSnapshot::create(['product_id' => $gated->id, 'price' => 80, 'source' => 'manual', 'created_at' => now()->subDays(20)]);
        PriceIntel::flush($gated->id);

        $gatedPost = Post::create([
            'user_id' => $author,
            'title' => 'Gated Card Review',
            'slug' => 'gated-card-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $gatedPost->products()->attach($gated->id, ['display_order' => 1]);
        $gatedPost->categories()->attach($category->id);

        // Ungated product: priced but no snapshot history → no verdict, ever.
        $ungated = Product::create([
            'category_id' => $category->id,
            'name' => 'Ungated Card Widget',
            'asin' => 'B00CARD02',
            'affiliate_url' => 'https://www.amazon.com/dp/B00CARD02',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 50,
            'description' => 'An ungated widget.',
        ]);
        $ungatedPost = Post::create([
            'user_id' => $author,
            'title' => 'Ungated Card Review',
            'slug' => 'ungated-card-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);
        $ungatedPost->products()->attach($ungated->id, ['display_order' => 1]);
        $ungatedPost->categories()->attach($category->id);

        // A tip in the same category takes the emerald accent and NEVER a price/
        // verdict row — cardIntel() is type-gated even if a product is attached.
        $tipPost = Post::create([
            'user_id' => $author,
            'title' => 'Handy Category Tip',
            'slug' => 'handy-category-tip',
            'type' => 'tech_tip',
            'body' => 'Tip body.',
            'status' => 'published',
            'published_at' => now()->subDays(3),
        ]);
        $tipPost->categories()->attach($category->id);

        $html = $this->get(route('category', $category->slug))->assertOk()->getContent();

        // Both review cards render and instant-nav to their posts.
        $this->assertStringContainsString('Gated Card Review', $html);
        $this->assertStringContainsString('Ungated Card Review', $html);
        $this->assertStringContainsString('href="'.route('posts.show', 'gated-card-review').'"', $html);
        $this->assertStringContainsString('wire:navigate', $html);

        // The tip card rendered with its emerald type badge, not a verdict.
        $this->assertStringContainsString('Handy Category Tip', $html);
        $this->assertStringContainsString('Tech Tip', $html);

        // Exactly one verdict chip: the gated card has it; the ungated review and
        // the tip never do.
        $this->assertSame(1, substr_count($html, 'Lowest tracked price'), 'only the gated card carries a verdict chip');
    }
}
