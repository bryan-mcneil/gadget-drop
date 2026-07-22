<?php

namespace App\Services;

use App\Models\MarketProduct;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Support\PriceIntel;
use Illuminate\Support\Facades\DB;

/**
 * Promotes a market-layer row (the wide, uncurated ASIN tracker — see
 * docs/MARKET-IMPORT.md) into the curated products catalog: creates the
 * Product prefilled from the market row and copies the ASIN's market price
 * history into product_price_snapshots with source='market', preserving the
 * original observation dates. Insert-with-history, never a move — the market
 * row stays put, and once the ASIN exists in both layers every future
 * market:import keeps the curated price current via mergeToCurated().
 *
 * The Product is created withoutEvents() on purpose: ProductObserver's
 * created-hook would append an "observed today" snapshot, but a promotion is
 * not a price observation. The seeded history already ends at the price the
 * market last saw, dated when it actually saw it, and price_checked_at is
 * set to last_seen_at so the public "Price checked {date}" label stays true.
 * PriceIntel::flush() is called explicitly since the observer path is skipped.
 */
class MarketPromotionService
{
    public function promote(MarketProduct $marketProduct, ?int $categoryId): Product
    {
        if (Product::where('asin', $marketProduct->asin)->exists()) {
            throw new \RuntimeException("A catalog product with ASIN {$marketProduct->asin} already exists.");
        }

        return DB::transaction(function () use ($marketProduct, $categoryId) {
            // products.image_url is 255 chars, the market column allows 500 —
            // a truncated URL would 404, so an over-long one is dropped.
            $imageUrl = $marketProduct->image_url;
            if ($imageUrl !== null && mb_strlen($imageUrl) > 255) {
                $imageUrl = null;
            }

            $product = Product::withoutEvents(fn () => Product::create([
                'category_id' => $categoryId,
                'name' => mb_substr($marketProduct->title, 0, 255),
                'brand' => $marketProduct->brand === null ? null : mb_substr($marketProduct->brand, 0, 255),
                'asin' => $marketProduct->asin,
                // Canonical /dp/ link, never the scraped url (tracking params;
                // market_products.url is data). Tag appended at /out/{product}.
                'affiliate_url' => 'https://www.amazon.com/dp/'.$marketProduct->asin,
                'image_url' => $imageUrl,
                'price' => $marketProduct->current_price,
                'price_checked_at' => $marketProduct->last_seen_at,
                'amazon_rating' => $marketProduct->rating,
                // products.amazon_review_count is an unsignedMediumInteger.
                'amazon_review_count' => $marketProduct->review_count === null
                    ? null
                    : min($marketProduct->review_count, 16_777_215),
                'description' => $marketProduct->description,
            ]));

            $rows = $marketProduct->snapshots()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($snapshot) => [
                    'product_id' => $product->id,
                    'price' => $snapshot->price,
                    'source' => 'market',
                    'created_at' => $snapshot->created_at,
                    'updated_at' => $snapshot->created_at,
                ])
                ->all();

            // market:import snapshots every row on first sight, but guard the
            // invariant anyway: every priced catalog product has history.
            if ($rows === []) {
                $rows = [[
                    'product_id' => $product->id,
                    'price' => $marketProduct->current_price,
                    'source' => 'market',
                    'created_at' => $marketProduct->last_seen_at,
                    'updated_at' => $marketProduct->last_seen_at,
                ]];
            }

            ProductPriceSnapshot::insert($rows);

            PriceIntel::flush($product->id);

            return $product;
        });
    }
}
