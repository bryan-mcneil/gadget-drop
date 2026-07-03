<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Support\PriceIntel;

/**
 * Keeps the price-snapshot history in sync with the products table: any save
 * that changes `price` stamps `price_checked_at` and appends one snapshot row.
 * Commands that refresh prices from an API set self::$source first so the
 * snapshot records where the number came from.
 */
class ProductObserver
{
    /** Source label recorded on snapshots created by the current process. */
    public static string $source = 'manual';

    public function saving(Product $product): void
    {
        if ($product->isDirty('price') && $product->price !== null && ! $product->isDirty('price_checked_at')) {
            $product->price_checked_at = now();
        }
    }

    public function created(Product $product): void
    {
        $this->snapshotIfChanged($product);
    }

    public function updated(Product $product): void
    {
        if ($product->wasChanged('price')) {
            $this->snapshotIfChanged($product);
        }
    }

    private function snapshotIfChanged(Product $product): void
    {
        if ($product->price === null) {
            return;
        }

        $latest = $product->priceSnapshots()->latest('created_at')->latest('id')->first();

        if ($latest && (float) $latest->price === (float) $product->price) {
            return;
        }

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price'      => $product->price,
            'source'     => self::$source,
        ]);

        PriceIntel::flush($product->id);
    }
}
