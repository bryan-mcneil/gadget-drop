<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\CanopyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function trackedProduct(float $price = 100): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Refresh Widget',
            'asin' => 'B00REFRESH',
            'affiliate_url' => 'https://www.amazon.com/dp/B00REFRESH',
            'price' => $price,
        ]);
        $product->forceFill(['price_checked_at' => now()->subDays(9)])->saveQuietly();

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Refresh Widget Review',
            'slug' => 'refresh-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return $product;
    }

    private function fakeCanopy(float $price): void
    {
        Http::fake([
            'graphql.canopyapi.co/*' => Http::response([
                'data' => [
                    'amazonProduct' => [
                        'title' => 'Refresh Widget',
                        'brand' => 'RefreshCo',
                        'mainImageUrl' => 'https://example.com/img.jpg',
                        'rating' => 4.4,
                        'ratingsTotal' => 1234,
                        'price' => ['value' => $price, 'currency' => 'USD'],
                        'featureBullets' => ['Bullet one'],
                    ],
                ],
            ]),
        ]);
    }

    public function test_no_configured_source_is_an_explicit_no_op(): void
    {
        Http::fake();
        $product = $this->trackedProduct(100);
        $before = $product->fresh()->price_checked_at;

        $this->artisan('prices:refresh')
            ->expectsOutputToContain('No price API configured')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertTrue($product->fresh()->price_checked_at->equalTo($before));
    }

    public function test_canopy_refresh_updates_price_and_snapshots_with_source(): void
    {
        config(['services.canopy.api_key' => 'test-key']);
        $this->fakeCanopy(79.99);
        $product = $this->trackedProduct(100);

        $this->artisan('prices:refresh')->assertSuccessful();

        $fresh = $product->fresh();
        $this->assertSame('79.99', (string) $fresh->price);
        $this->assertSame('4.4', (string) $fresh->amazon_rating);
        $this->assertTrue($fresh->price_checked_at->isToday());
        $this->assertDatabaseHas('product_price_snapshots', [
            'product_id' => $product->id,
            'price' => 79.99,
            'source' => 'canopy',
        ]);
        $this->assertSame(1, app(CanopyApiService::class)->usageThisMonth());
    }

    public function test_unchanged_price_stamps_the_check_without_a_snapshot(): void
    {
        config(['services.canopy.api_key' => 'test-key']);
        $this->fakeCanopy(100.00);
        $product = $this->trackedProduct(100);
        $snapshotsBefore = $product->priceSnapshots()->count();

        $this->artisan('prices:refresh')->assertSuccessful();

        $this->assertSame($snapshotsBefore, $product->priceSnapshots()->count());
        $this->assertTrue($product->fresh()->price_checked_at->isToday());
    }

    public function test_the_monthly_budget_is_a_hard_stop(): void
    {
        config(['services.canopy.api_key' => 'test-key', 'services.canopy.monthly_budget' => 5]);
        Http::fake();
        Cache::put('canopy.usage.'.now()->format('Y-m'), 5, now()->addDays(40));
        $this->trackedProduct(100);

        $this->artisan('prices:refresh')
            ->expectsOutputToContain('budget exhausted')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_pa_api_wins_over_canopy_when_both_are_configured(): void
    {
        config([
            'services.canopy.api_key' => 'canopy-key',
            'services.amazon.pa_access_key' => 'ak',
            'services.amazon.pa_secret_key' => 'sk',
            'services.amazon.pa_partner_tag' => 'tag-20',
        ]);

        Http::fake([
            'webservices.amazon.com/*' => Http::response([
                'ItemsResult' => ['Items' => [[
                    'ItemInfo' => ['Title' => ['DisplayValue' => 'Refresh Widget']],
                    'Offers' => ['Listings' => [['Price' => ['Amount' => 88.00]]]],
                ]]],
            ]),
            'graphql.canopyapi.co/*' => Http::response(['data' => []]),
        ]);

        $product = $this->trackedProduct(100);

        $this->artisan('prices:refresh --limit=1')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'webservices.amazon.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'canopyapi.co'));
        $this->assertDatabaseHas('product_price_snapshots', [
            'product_id' => $product->id,
            'source' => 'pa_api',
        ]);
    }
}
