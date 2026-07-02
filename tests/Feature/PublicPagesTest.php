<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropPricePuzzle;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function publicRoutes(): array
    {
        return [
            'home'      => ['/', 'GadgetDrop'],
            'about'     => ['/about', 'What we do'],
            'privacy'   => ['/privacy', 'Privacy Policy'],
            'terms'     => ['/terms', 'Terms of Service'],
            'contact'   => ['/contact', 'Contact GadgetDrop'],
            'cookies'   => ['/cookies', 'Cookie Policy'],
            'news'      => ['/news', 'Tech News'],
            'search'    => ['/search', 'Find your next drop'],
            'tools'     => ['/tools', 'Online Tools'],
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

    public function test_home_renders_the_drop_price_game_without_leaking_the_price(): void
    {
        // The puzzle product is attached to a published article — the exact pool
        // that Top Picks / Spotlight draw from — so it WOULD surface there with
        // its price unless the controller excludes it. The post also gives the
        // hero band a slide (the game island lives inside it).
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'A Published Drop',
            'slug'         => 'a-published-drop',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => 'Secret Mystery Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url'     => 'https://example.com/img.jpg',
            'price'         => 4242,
            'description'   => 'A test gadget.',
        ]);
        $product->posts()->attach($post->id);

        DropPricePuzzle::create([
            'puzzle_number'        => 7,
            'date'                 => now()->toDateString(),
            'product_id'           => $product->id,
            'price'                => 4242,
            'product_name'         => $product->name,
            'product_image_url'    => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at'            => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Guess today\'s price', false)   // the game island rendered
            ->assertSee('Secret Mystery Widget', false)  // product shown openly (no price)
            ->assertDontSee('4242')                      // the snapshot answer, never
            ->assertDontSee('4,242');                    // …nor leaked via Top Picks / Spotlight
    }
}
