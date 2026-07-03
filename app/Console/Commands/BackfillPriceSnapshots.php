<?php

namespace App\Console\Commands;

use App\Models\DropPricePuzzle;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Support\PriceIntel;
use Illuminate\Console\Command;

/**
 * Seed the price-snapshot history from data the site already collected:
 *  1. Every locked Drop Price puzzle recorded a real (whole-dollar) Amazon
 *     price for its product on its date — import each as a snapshot.
 *  2. Every priced product with no snapshots yet gets an 'initial' snapshot
 *     so tracking starts from a known point.
 *
 * Idempotent — safe to re-run; existing snapshots are never duplicated.
 *
 *   php artisan prices:backfill --dry-run
 *   php artisan prices:backfill
 */
class BackfillPriceSnapshots extends Command
{
    protected $signature = 'prices:backfill {--dry-run : Report what would be written without writing}';

    protected $description = 'Seed product price snapshots from Drop Price puzzle history and current prices.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $tag = $dry ? '[dry-run] ' : '';

        $fromPuzzles = 0;

        DropPricePuzzle::query()
            ->whereNotNull('product_id')
            ->whereNotNull('locked_at')
            ->where('price', '>', 0)
            ->orderBy('date')
            ->each(function (DropPricePuzzle $puzzle) use ($dry, &$fromPuzzles) {
                $exists = ProductPriceSnapshot::where('product_id', $puzzle->product_id)
                    ->where('source', 'drop_price')
                    ->whereDate('created_at', $puzzle->date->toDateString())
                    ->exists();

                if ($exists) {
                    return;
                }

                if (! $dry) {
                    // Whole-dollar price (the game rounds at lock time) — fine for
                    // trend math, and the source label records the provenance.
                    ProductPriceSnapshot::create([
                        'product_id' => $puzzle->product_id,
                        'price'      => $puzzle->price,
                        'source'     => 'drop_price',
                        'created_at' => $puzzle->date->copy()->setTime(12, 0),
                    ]);
                }

                $fromPuzzles++;
            });

        $this->info("{$tag}{$fromPuzzles} snapshot(s) imported from Drop Price puzzles.");

        $initial = 0;

        Product::query()
            ->whereNotNull('price')
            ->whereDoesntHave('priceSnapshots')
            ->each(function (Product $product) use ($dry, &$initial) {
                if (! $dry) {
                    ProductPriceSnapshot::create([
                        'product_id' => $product->id,
                        'price'      => $product->price,
                        'source'     => 'initial',
                        // Best available date for when this price was observed.
                        'created_at' => $product->updated_at ?? now(),
                    ]);

                    if ($product->price_checked_at === null) {
                        // forceFill+saveQuietly: stamp without tripping the
                        // observer into a duplicate snapshot.
                        $product->forceFill(['price_checked_at' => $product->updated_at ?? now()])->saveQuietly();
                    }
                }

                $initial++;
            });

        $this->info("{$tag}{$initial} initial snapshot(s) for products with no history.");

        if (! $dry) {
            Product::pluck('id')->each(fn ($id) => PriceIntel::flush($id));
        }

        $this->info("{$tag}Done.");

        return self::SUCCESS;
    }
}
