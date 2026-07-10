<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DropPricePuzzle;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DropPriceCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Build a product that is eligible for the daily puzzle (has image, price, published post). */
    private function eligibleProduct(string $name = 'Test Gadget', float $price = 49.99): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $price,
            'description' => 'A test gadget.',
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "Post for {$name}",
            'slug' => 'post-'.Str::random(8),
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $product->posts()->attach($post->id);

        return $product;
    }

    /** Record that $product was the locked puzzle on $date (a past puzzle). */
    private function puzzleFor(Product $product, Carbon $date): DropPricePuzzle
    {
        return DropPricePuzzle::create([
            'puzzle_number' => $this->nextNumber(),
            'date' => $date->toDateString(),
            'product_id' => $product->id,
            'price' => (int) round((float) $product->price),
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
            'locked_at' => $date,
        ]);
    }

    private function nextNumber(): int
    {
        return (int) (DropPricePuzzle::max('puzzle_number') ?? 0) + 1;
    }

    public function test_auto_pick_locks_a_puzzle_with_integer_price(): void
    {
        $product = $this->eligibleProduct(price: 199.99);

        $this->artisan('dropprice:lock')->assertExitCode(0);

        $puzzle = DropPricePuzzle::sole();
        $this->assertSame($product->id, $puzzle->product_id);
        $this->assertSame(200, $puzzle->price);              // rounded to nearest dollar
        $this->assertSame(1, $puzzle->puzzle_number);        // first puzzle
        $this->assertFalse($puzzle->is_preset);
        $this->assertNotNull($puzzle->locked_at);
        $this->assertSame($product->name, $puzzle->product_name);
        $this->assertSame($product->id, $puzzle->affiliate_product_id);
    }

    public function test_ineligible_products_are_skipped(): void
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        // No image.
        Product::create(['category_id' => $category->id, 'name' => 'No Image', 'price' => 30, 'image_url' => null, 'affiliate_url' => 'x', 'description' => 'd']);
        // Zero price.
        Product::create(['category_id' => $category->id, 'name' => 'Free', 'price' => 0, 'image_url' => 'https://e/i.jpg', 'affiliate_url' => 'x', 'description' => 'd']);
        // No published post (draft).
        $draftProduct = Product::create(['category_id' => $category->id, 'name' => 'Draft Only', 'price' => 40, 'image_url' => 'https://e/i.jpg', 'affiliate_url' => 'x', 'description' => 'd']);
        $draft = Post::create(['user_id' => User::factory()->create()->id, 'title' => 'Draft', 'slug' => 'draft', 'body' => 'b', 'status' => 'draft']);
        $draftProduct->posts()->attach($draft->id);

        // The only eligible one.
        $good = $this->eligibleProduct('The Good One');

        $this->artisan('dropprice:lock')->assertExitCode(0);

        $this->assertSame($good->id, DropPricePuzzle::sole()->product_id);
    }

    public function test_no_eligible_product_warns_and_creates_nothing(): void
    {
        // A product with no published post — nothing eligible.
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        Product::create(['category_id' => $category->id, 'name' => 'Lonely', 'price' => 40, 'image_url' => 'https://e/i.jpg', 'affiliate_url' => 'x', 'description' => 'd']);

        $this->artisan('dropprice:lock')->assertExitCode(0);

        $this->assertSame(0, DropPricePuzzle::count());
    }

    public function test_run_is_idempotent_for_the_same_date(): void
    {
        $this->eligibleProduct();

        $this->artisan('dropprice:lock')->assertExitCode(0);
        $this->artisan('dropprice:lock')->assertExitCode(0);

        $this->assertSame(1, DropPricePuzzle::count());
    }

    public function test_puzzle_numbers_increment_across_days(): void
    {
        $this->eligibleProduct('Day One');
        $this->eligibleProduct('Day Two');

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->artisan('dropprice:lock', ['--date' => $today])->assertExitCode(0);
        $this->artisan('dropprice:lock', ['--date' => $tomorrow])->assertExitCode(0);

        $this->assertSame(1, DropPricePuzzle::whereDate('date', $today)->value('puzzle_number'));
        $this->assertSame(2, DropPricePuzzle::whereDate('date', $tomorrow)->value('puzzle_number'));
    }

    public function test_used_product_is_never_reused_while_unused_remain(): void
    {
        $used = $this->eligibleProduct('Already Used');
        $fresh = $this->eligibleProduct('Fresh');

        // $used was a previous puzzle.
        $this->puzzleFor($used, now()->subDays(10));

        $this->artisan('dropprice:lock')->assertExitCode(0);

        $this->assertSame($fresh->id, DropPricePuzzle::whereDate('date', now()->toDateString())->value('product_id'));
    }

    public function test_falls_back_to_least_recently_used_when_pool_is_dry(): void
    {
        // Two eligible products, both already used — no unused product remains.
        $older = $this->eligibleProduct('Used Long Ago');
        $newer = $this->eligibleProduct('Used Recently');

        $this->puzzleFor($older, now()->subDays(20));
        $this->puzzleFor($newer, now()->subDays(2));

        $this->artisan('dropprice:lock')->assertExitCode(0);

        // The least-recently-used one is reused (never the most recent puzzle).
        $today = DropPricePuzzle::whereDate('date', now()->toDateString())->first();
        $this->assertSame($older->id, $today->product_id);
        $this->assertNotNull($today->puzzle_number);
    }

    public function test_preset_for_a_future_date_is_queued_unlocked(): void
    {
        $product = $this->eligibleProduct('Queued Pick', 75.49);
        $future = now()->addDays(5)->toDateString();

        $this->artisan('dropprice:lock', ['--product' => $product->id, '--date' => $future])->assertExitCode(0);

        $puzzle = DropPricePuzzle::sole();
        $this->assertSame($product->id, $puzzle->product_id);
        $this->assertTrue($puzzle->is_preset);
        $this->assertNull($puzzle->puzzle_number);   // number assigned only at lock
        $this->assertNull($puzzle->locked_at);
        $this->assertSame(75, $puzzle->price);
    }

    public function test_preset_for_today_locks_immediately(): void
    {
        $product = $this->eligibleProduct('Forced Today', 120.00);

        $this->artisan('dropprice:lock', ['--product' => $product->id, '--date' => now()->toDateString()])->assertExitCode(0);

        $puzzle = DropPricePuzzle::sole();
        $this->assertTrue($puzzle->is_preset);
        $this->assertSame($product->id, $puzzle->product_id);
        $this->assertSame(1, $puzzle->puzzle_number);
        $this->assertNotNull($puzzle->locked_at);
        $this->assertSame(120, $puzzle->price);
    }

    public function test_queued_preset_locks_on_its_date_and_gets_a_number(): void
    {
        $product = $this->eligibleProduct('Queued', 60.00);
        $date = now()->addDays(3)->toDateString();

        // Queue it for a future date (stays unlocked).
        $this->artisan('dropprice:lock', ['--product' => $product->id, '--date' => $date])->assertExitCode(0);

        // The scheduled run for that date should honour the preset and lock it.
        $this->artisan('dropprice:lock', ['--date' => $date])->assertExitCode(0);

        $puzzle = DropPricePuzzle::sole();
        $this->assertTrue($puzzle->is_preset);
        $this->assertSame(1, $puzzle->puzzle_number);
        $this->assertNotNull($puzzle->locked_at);
    }

    public function test_unknown_preset_product_fails(): void
    {
        $this->artisan('dropprice:lock', ['--product' => 9999])->assertExitCode(1);

        $this->assertSame(0, DropPricePuzzle::count());
    }
}
