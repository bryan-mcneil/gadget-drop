<?php

namespace App\Console\Commands;

use App\Support\ImageVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill responsive WebP variants for every uploaded image. Safe to run on
 * production and idempotent (re-running only fills gaps). It never modifies the
 * originals, so no DB image references change.
 *
 * Usage: php artisan images:optimize [--force]
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--force : Regenerate variants even if they already exist}';

    protected $description = 'Generate responsive WebP variants for uploaded images (idempotent).';

    public function handle(): int
    {
        if (! ImageVariants::supported()) {
            $this->error('GD WebP support is not available (imagewebp missing). Aborting.');

            return self::FAILURE;
        }

        // Decoding large images with GD is memory-hungry; give the backfill room
        // (CLI only — the upload path stays guarded by ImageVariants itself).
        @ini_set('memory_limit', '1024M');

        $disk  = Storage::disk('public');
        $force = (bool) $this->option('force');

        $images = collect($disk->allFiles('uploads'))
            ->filter(fn ($f) => in_array(
                strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                ImageVariants::SOURCE_EXTENSIONS,
                true,
            ))
            ->values();

        if ($images->isEmpty()) {
            $this->info('No images found under uploads/.');

            return self::SUCCESS;
        }

        $this->info("Processing {$images->count()} image(s)".($force ? ' (force)' : '').'...');
        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        $variantCount = 0;
        foreach ($images as $relPath) {
            $variantCount += count(ImageVariants::generate($relPath, $force));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$variantCount} variant file(s) present.");

        return self::SUCCESS;
    }
}
