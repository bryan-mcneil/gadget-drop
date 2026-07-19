<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceSnapshotTest extends TestCase
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
            'asin' => "B00WID{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00WID',
            'price' => 100,
        ], $overrides));
    }

    public function test_creating_a_priced_product_snapshots_and_stamps_checked_at(): void
    {
        $product = $this->makeProduct(['price' => 149.99]);

        $this->assertNotNull($product->price_checked_at);
        $this->assertDatabaseHas('product_price_snapshots', [
            'product_id' => $product->id,
            'price' => 149.99,
            'source' => 'manual',
        ]);
        $this->assertSame(1, $product->priceSnapshots()->count());
    }

    public function test_price_change_appends_exactly_one_snapshot(): void
    {
        $product = $this->makeProduct(['price' => 100]);

        $product->update(['price' => 89.99]);

        $this->assertSame(2, $product->priceSnapshots()->count());
        $this->assertSame('89.99', (string) $product->priceSnapshots()->latest('id')->first()->price);
    }

    public function test_saving_without_a_price_change_snapshots_nothing(): void
    {
        $product = $this->makeProduct(['price' => 100]);
        $checkedAt = $product->price_checked_at;

        $this->travel(1)->hours();
        $product->update(['name' => 'Renamed Widget']);

        $this->assertSame(1, $product->priceSnapshots()->count());
        $this->assertTrue($product->fresh()->price_checked_at->equalTo($checkedAt));
    }

    public function test_a_confirmed_check_records_one_same_price_snapshot_per_day(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $product = $this->makeProduct(['price' => 100]);

        // Same-day confirm: the creation snapshot already proves today's
        // price was observed — no extra row.
        Carbon::setTestNow('2026-08-01 14:00:00');
        $product->forceFill(['price_checked_at' => now()])->save();
        $this->assertSame(1, $product->priceSnapshots()->count());

        // Days later the check is new information: one same-price row.
        Carbon::setTestNow('2026-08-04 10:00:00');
        $product->forceFill(['price_checked_at' => now()])->save();

        $this->assertSame(2, $product->priceSnapshots()->count());
        $latest = $product->priceSnapshots()->latest('id')->first();
        $this->assertSame('100.00', (string) $latest->price);
        $this->assertSame('manual', $latest->source);

        // A second confirm the same day adds nothing.
        Carbon::setTestNow('2026-08-04 16:00:00');
        $product->forceFill(['price_checked_at' => now()])->save();
        $this->assertSame(2, $product->priceSnapshots()->count());
    }

    public function test_a_confirmed_check_only_history_stays_typical_never_lowest(): void
    {
        // A flat history built purely from confirmed checks passes the
        // honesty gate's span, but with zero variation the verdict must be
        // "typical" — a checked-but-never-moved price can't masquerade as a
        // low (and so can never qualify for /deals).
        Carbon::setTestNow('2026-08-01 10:00:00');
        $product = $this->makeProduct(['price' => 100]);

        foreach (['2026-08-06', '2026-08-11', '2026-08-16'] as $day) {
            Carbon::setTestNow("{$day} 10:00:00");
            $product->forceFill(['price_checked_at' => now()])->save();
        }

        $this->assertSame(4, $product->priceSnapshots()->count());
        $this->assertSame('typical', PriceIntel::stats($product->id)['verdict']);
    }

    public function test_unpriced_products_are_ignored(): void
    {
        $product = $this->makeProduct(['price' => null]);

        $this->assertNull($product->price_checked_at);
        $this->assertSame(0, $product->priceSnapshots()->count());
    }
}
