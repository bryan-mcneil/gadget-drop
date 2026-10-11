<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Support\PriceIntel;

/**
 * Keeps the price-snapshot history in sync with the products table: any save
 * that changes `price` stamps `price_checked_at` and appends one snapshot row,
 * and a save that only refreshes `price_checked_at` (a confirmed check — the
 * price was looked at and had not moved) appends at most one same-price
 * snapshot per day. Checks must be durable rows, not just a mutable stamp:
 * the Truth Report event-observation gate can only trust snapshots, and the
 * cached "Price checked" label only refreshes on the flush a snapshot triggers.
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
        } elseif ($product->wasChanged('price_checked_at')) {
            $this->snapshotConfirmedCheck($product);
        }
    }

    /**
     * A checked-but-unchanged price becomes a same-price snapshot, capped at
     * one per product per day — enough to prove the product was observed
     * without letting repeated clicks pad the history.
     */
    private function snapshotConfirmedCheck(Product $product): void
    {
        if ($product->price === null) {
            return;
        }

        $latest = $product->priceSnapshots()->latest('created_at')->latest('id')->first();

        if ($latest && $latest->created_at->isToday()) {
            return;
        }

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $product->price,
            'source' => self::$source,
        ]);

        PriceIntel::flush($product->id);
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
            'price' => $product->price,
            'source' => self::$source,
        ]);

        PriceIntel::flush($product->id);
    }
}
