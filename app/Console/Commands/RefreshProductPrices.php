<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\AmazonProductService;
use App\Services\CanopyApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled price refresh with pluggable sources, in priority order:
 *   1. Amazon PA-API  (AmazonProductService) — once the site qualifies
 *   2. Canopy API     (CanopyApiService)     — free-tier interim source
 *   3. neither configured → explicit no-op (manual /admin/prices flow remains)
 *
 * Products refresh stalest-first, with anything currently on the /deals feed
 * moved to the front so public numbers stay freshest. Price changes are
 * snapshotted by ProductObserver with the source recorded; unchanged prices
 * refresh the "Price checked" stamp and are recorded by the observer as a
 * same-price check snapshot (max one per day) so the check stays durable.
 */
class RefreshProductPrices extends Command
{
    protected $signature = 'prices:refresh {--limit= : Max products to refresh this run}';

    protected $description = 'Refresh product prices from PA-API or Canopy (no-op when neither is configured).';

    public function handle(AmazonProductService $amazon, CanopyApiService $canopy): int
    {
        if ($amazon->isConfigured()) {
            $client = $amazon;
            $source = 'pa_api';
            $limit = (int) ($this->option('limit') ?: 40);
        } elseif ($canopy->isConfigured()) {
            $client = $canopy;
            $source = 'canopy';
            $remaining = $canopy->requestsRemainingThisMonth();
            $limit = min((int) ($this->option('limit') ?: config('services.canopy.daily_limit', 3)), $remaining);

            if ($limit < 1) {
                $this->info("Canopy monthly budget exhausted ({$canopy->usageThisMonth()} used) — skipping until next month.");

                return self::SUCCESS;
            }
        } else {
            $this->info('No price API configured (PA-API or Canopy) — skipping. Manual refresh: /admin/prices.');

            return self::SUCCESS;
        }

        $products = $this->productsByPriority($limit);

        if ($products->isEmpty()) {
            $this->info('No products with an ASIN and a published post to refresh.');

            return self::SUCCESS;
        }

        $this->info("Refreshing {$products->count()} product(s) via {$source}.");

        foreach ($products as $index => $product) {
            if ($index > 0) {
                sleep(1); // PA-API allows 1 rps; being equally polite to Canopy costs nothing.
            }

            $data = $client->lookup($product->asin);

            if ($data === null) {
                $this->warn("  {$product->name}: lookup failed — left untouched.");

                continue;
            }

            ProductObserver::$source = $source;

            $updates = array_filter([
                'price' => $data['price'],
                'amazon_rating' => $data['amazon_rating'],
                'amazon_review_count' => $data['amazon_review_count'],
            ], fn ($v) => $v !== null);

            if (! empty($updates)) {
                $product->update($updates);
            }

            // A successful check without a price change still counts as checked.
            if (! $product->wasChanged('price')) {
                $product->forceFill(['price_checked_at' => now()])->save();
            }

            ProductObserver::$source = 'manual';

            $priceLabel = $data['price'] !== null ? '$'.number_format($data['price'], 2) : 'no price returned';
            $this->line("  {$product->name}: {$priceLabel}");
        }

        if ($source === 'canopy') {
            $this->info("Canopy usage this month: {$canopy->usageThisMonth()} of ".config('services.canopy.monthly_budget', 90).'.');
        }

        return self::SUCCESS;
    }

    /**
     * Stalest-first among products with an ASIN and a published post, with
     * current /deals entries promoted to the front of the queue.
     */
    private function productsByPriority(int $limit)
    {
        $dealIds = [];

        try {
            $dealIds = collect(Cache::get('deals.feed', []))->pluck('product_id')->all();
        } catch (\Throwable) {
            // No cached feed — plain staleness order.
        }

        return Product::query()
            ->whereNotNull('asin')
            ->whereHas('posts', fn ($q) => $q->published())
            ->orderByRaw('price_checked_at IS NOT NULL')
            ->orderBy('price_checked_at')
            ->get()
            ->sortByDesc(fn ($p) => in_array($p->id, $dealIds, true) ? 1 : 0)
            ->take($limit)
            ->values();
    }
}
