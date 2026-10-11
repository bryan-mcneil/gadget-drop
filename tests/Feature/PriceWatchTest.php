<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceWatch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceWatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => "Widget {$i}",
            'asin' => "B00WATCH{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00WATCH',
            'price' => 100,
        ], $overrides));
    }

    private function makeWatch(array $overrides = []): PriceWatch
    {
        $attrs = array_merge([
            'email' => 'buyer@example.test',
            'purchase_price' => 100,
            'purchased_at' => today(),
            'verified_at' => now(),
        ], $overrides);

        // Only mint a throwaway product when the caller didn't supply one.
        $attrs['product_id'] ??= $this->makeProduct()->id;

        // forceCreate so tests can seed the non-fillable state stamps
        // (verified_at/notified_at); token + expires_at still derive in booted().
        return PriceWatch::forceCreate($attrs);
    }

    public function test_expires_at_is_computed_thirty_days_after_purchase_on_create(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $watch = $this->makeWatch(['purchased_at' => '2026-07-15']);

        $this->assertSame('2026-08-14', $watch->expires_at->toDateString());
    }

    public function test_a_token_is_generated_on_create(): void
    {
        $watch = $this->makeWatch();

        $this->assertNotEmpty($watch->token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $watch->token);
    }

    public function test_savings_returns_the_drop_and_never_goes_negative(): void
    {
        $watch = $this->makeWatch(['purchase_price' => 100]);

        $this->assertSame(23.0, $watch->savings(77));
        $this->assertSame(0.0, $watch->savings(120)); // price rose → no "negative saving"
    }

    public function test_active_scope_returns_only_verified_unnotified_in_window(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $active = $this->makeWatch(['purchased_at' => today()]);
        $unverified = $this->makeWatch(['purchased_at' => today(), 'verified_at' => null]);
        $notified = $this->makeWatch(['purchased_at' => today(), 'notified_at' => now()]);
        $expired = $this->makeWatch(['purchased_at' => now()->subDays(40)]); // expires 10 days ago

        $ids = PriceWatch::active()->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($unverified->id));
        $this->assertFalse($ids->contains($notified->id));
        $this->assertFalse($ids->contains($expired->id));
    }

    public function test_active_scope_includes_a_watch_expiring_today(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        // Purchased 30 days ago → expires today; the window's last day still counts.
        $watch = $this->makeWatch(['purchased_at' => now()->subDays(30)]);

        $this->assertSame(today()->toDateString(), $watch->expires_at->toDateString());
        $this->assertTrue(PriceWatch::active()->pluck('id')->contains($watch->id));
    }

    public function test_expired_scope_returns_only_past_window_watches(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        $active = $this->makeWatch(['purchased_at' => today()]);
        $expired = $this->makeWatch(['purchased_at' => now()->subDays(40)]);

        $ids = PriceWatch::expired()->pluck('id');

        $this->assertTrue($ids->contains($expired->id));
        $this->assertFalse($ids->contains($active->id));
    }

    public function test_prunable_removes_watches_expired_over_sixty_days(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');

        // expires 61 days ago (purchased 91 days ago + 30-day window) → prunable.
        $stale = $this->makeWatch(['purchased_at' => now()->subDays(91)]);
        // expires 59 days ago → still inside the 60-day grace → kept.
        $recent = $this->makeWatch(['purchased_at' => now()->subDays(89)]);

        (new PriceWatch)->pruneAll();

        $this->assertDatabaseMissing('price_watches', ['id' => $stale->id]);
        $this->assertDatabaseHas('price_watches', ['id' => $recent->id]);
    }

    public function test_deleting_a_product_cascades_to_its_watches(): void
    {
        $product = $this->makeProduct();
        $watch = $this->makeWatch(['product_id' => $product->id]);

        $product->delete();

        $this->assertDatabaseMissing('price_watches', ['id' => $watch->id]);
    }
}
