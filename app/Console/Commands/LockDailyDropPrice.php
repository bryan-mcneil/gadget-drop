<?php

namespace App\Console\Commands;

use App\Models\DropPricePuzzle;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LockDailyDropPrice extends Command
{
    /**
     * --product / --date queue (or instantly lock, if the date is today/past) an
     * admin override; with no options the command auto-picks today's puzzle.
     */
    protected $signature = 'dropprice:lock
        {--product= : Product ID to force for the target date (admin override)}
        {--date= : Target date (YYYY-MM-DD, UTC); defaults to today}';

    protected $description = 'Lock in the daily Drop Price puzzle (auto-pick or admin override).';

    public function handle(): int
    {
        $date = $this->resolveDate();

        if ($date === null) {
            $this->error('Invalid --date; use YYYY-MM-DD.');

            return self::FAILURE;
        }

        // --- Admin override (CLI): queue/replace a preset for the target date ---
        if ($this->option('product') !== null) {
            return $this->preset($date, (int) $this->option('product'));
        }

        // --- Daily lock path ---
        $existing = DropPricePuzzle::whereDate('date', $date->toDateString())->first();

        if ($existing && $existing->locked_at !== null) {
            $this->info("Drop Price #{$existing->puzzle_number} already locked for {$date->toDateString()} — nothing to do.");

            return self::SUCCESS;
        }

        // A preset queued ahead of time: honour the admin's product, finalize it.
        if ($existing) {
            return $this->lock($existing);
        }

        return $this->autoPick($date);
    }

    /**
     * Create (or update) a preset row for $date. If the date is today/past it is
     * locked immediately so a same-day override takes effect now; a future date is
     * left queued for its day's scheduled run to lock (re-snapshotting the price).
     */
    private function preset(Carbon $date, int $productId): int
    {
        $product = Product::find($productId);

        if (! $product) {
            $this->error("Product #{$productId} not found.");

            return self::FAILURE;
        }

        $puzzle = DropPricePuzzle::firstOrNew(['date' => $date->toDateString()]);

        if ($puzzle->locked_at !== null) {
            $this->error("A puzzle is already locked for {$date->toDateString()} (#{$puzzle->puzzle_number}); refusing to overwrite.");

            return self::FAILURE;
        }

        $puzzle->fill([
            'product_id' => $product->id,
            'is_preset' => true,
        ] + $this->snapshotFields($product))->save();

        $this->info("Queued preset for {$date->toDateString()}: {$product->name} (\${$puzzle->price}).");

        // Take effect now for today/past dates; future presets lock on their day.
        if ($date->lessThanOrEqualTo($this->today())) {
            return $this->lock($puzzle);
        }

        return self::SUCCESS;
    }

    /**
     * Finalize a puzzle row for its date: re-snapshot the price from the live
     * product (if it still exists), assign the next sequential number, stamp
     * locked_at, and bust the homepage cache.
     */
    private function lock(DropPricePuzzle $puzzle): int
    {
        if ($puzzle->product) {
            $puzzle->fill($this->snapshotFields($puzzle->product));
        }

        $puzzle->puzzle_number = $this->nextNumber();
        $puzzle->locked_at = now();
        $puzzle->save();

        Cache::forget('dropprice.today');

        $this->info("Locked Drop Price #{$puzzle->puzzle_number} for {$puzzle->date->toDateString()}: {$puzzle->product_name} (\${$puzzle->price}).");

        return self::SUCCESS;
    }

    /**
     * Pick an eligible product and lock a fresh puzzle. No eligible product is a
     * logged warning, never a throw — the homepage must keep serving yesterday's.
     */
    private function autoPick(Carbon $date): int
    {
        // Prefer a product never used as a puzzle — every puzzle stays a unique
        // product so the archive is always re-playable. The daily-drop pipeline
        // adds products faster than the game consumes them, so this rarely runs dry.
        $usedProductIds = DropPricePuzzle::whereNotNull('product_id')
            ->pluck('product_id')
            ->all();

        $product = $this->eligibleProducts()
            ->whereNotIn('id', $usedProductIds)
            ->inRandomOrder()
            ->first()
            // Pool exhausted: reuse the least-recently-used product so the homepage
            // never serves yesterday's puzzle again (it sorts last by definition).
            ?? $this->leastRecentlyUsedProduct();

        if (! $product) {
            $message = "dropprice:lock found no eligible product for {$date->toDateString()}; keeping the previous puzzle.";
            $this->warn($message);
            Log::warning($message);

            return self::SUCCESS;
        }

        $puzzle = DropPricePuzzle::create([
            'puzzle_number' => $this->nextNumber(),
            'date' => $date->toDateString(),
            'product_id' => $product->id,
            'locked_at' => now(),
            'is_preset' => false,
        ] + $this->snapshotFields($product));

        Cache::forget('dropprice.today');

        $this->info("Locked Drop Price #{$puzzle->puzzle_number} for {$date->toDateString()}: {$product->name} (\${$puzzle->price}).");

        return self::SUCCESS;
    }

    /** Base query for products that can ever be a puzzle (has image, price, published post). */
    private function eligibleProducts(): Builder
    {
        return Product::query()
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->where('price', '>', 0)
            ->whereHas('posts', fn ($q) => $q->published());
    }

    /** Fallback when every eligible product has been used: the one used longest ago. */
    private function leastRecentlyUsedProduct(): ?Product
    {
        return $this->eligibleProducts()
            ->select('products.*')
            ->selectSub(
                DropPricePuzzle::selectRaw('MAX(date)')->whereColumn('product_id', 'products.id'),
                'last_used_at'
            )
            ->orderBy('last_used_at')
            ->first();
    }

    /**
     * The fields frozen from the live product onto a puzzle row — the whole-
     * dollar answer plus the display snapshots that keep old puzzles rendering
     * after the product is edited or deleted. Shared by preset(), lock(), and
     * autoPick() so the snapshot can never drift between the three paths.
     */
    private function snapshotFields(Product $product): array
    {
        return [
            'price' => $this->snapshotPrice($product),
            'product_name' => $product->name,
            'product_image_url' => $product->image_url,
            'affiliate_product_id' => $product->id,
        ];
    }

    /** Round the decimal product price to the nearest whole dollar (the answer). */
    private function snapshotPrice(Product $product): int
    {
        return (int) round((float) $product->price);
    }

    private function nextNumber(): int
    {
        return (int) (DropPricePuzzle::max('puzzle_number') ?? 0) + 1;
    }

    private function today(): Carbon
    {
        return Carbon::now()->startOfDay();
    }

    private function resolveDate(): ?Carbon
    {
        $raw = $this->option('date');

        if ($raw === null || $raw === '') {
            return $this->today();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
