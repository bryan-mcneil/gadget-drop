<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Support\NavigationData;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Merge thin categories into their bigger hubs per config('site.category_map'),
 * then delete the emptied sources. Companion to the 301s in routes/web.php.
 * Idempotent: sources that no longer exist are skipped.
 *
 *   php artisan categories:consolidate --dry-run   # review the moves first
 *   php artisan categories:consolidate
 *
 * Follow-up (manual, in /admin/categories): write an 80-150 word description
 * for each surviving category and re-home any post the bulk move left in an
 * odd hub (Accessories/Productivity content especially).
 */
class ConsolidateCategories extends Command
{
    protected $signature = 'categories:consolidate {--dry-run : Report what would change without writing}';

    protected $description = 'Merge thin categories into their target hubs per config(site.category_map).';

    public function handle(): int
    {
        $map = config('site.category_map', []);

        if (empty($map)) {
            $this->error('site.category_map is empty — nothing to consolidate.');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $tag = $dry ? '[dry-run] ' : '';

        foreach ($map as $sourceSlug => $targetSlug) {
            $source = Category::where('slug', $sourceSlug)->first();

            if (! $source) {
                $this->line("{$tag}skip: '{$sourceSlug}' does not exist (already merged?)");
                continue;
            }

            $target = Category::where('slug', $targetSlug)->first();

            if (! $target) {
                $name = config("site.category_renames.{$targetSlug}")
                    ?? Str::of($targetSlug)->replace('-', ' ')->title()->toString();
                $this->info("{$tag}creating target category '{$targetSlug}' ({$name})");
                $target = $dry
                    ? null
                    : Category::create(['slug' => $targetSlug, 'name' => $name]);
            }

            $postIds      = $source->posts()->pluck('posts.id');
            $productCount = $source->products()->count();

            $this->info("{$tag}'{$sourceSlug}' → '{$targetSlug}': moving {$postIds->count()} post(s), {$productCount} product(s)");

            if (! $dry) {
                // Attach target to every post of the source (ignoring posts that
                // already carry it), detach the source, repoint products.
                $target->posts()->syncWithoutDetaching($postIds->all());
                $source->posts()->detach();
                $source->products()->update(['category_id' => $target->id]);
                $source->delete();
            }
        }

        foreach (config('site.category_renames', []) as $slug => $name) {
            $category = Category::where('slug', $slug)->first();

            if ($category && $category->name !== $name) {
                $this->info("{$tag}renaming '{$slug}' display name: '{$category->name}' → '{$name}'");
                if (! $dry) {
                    $category->update(['name' => $name]);
                }
            }
        }

        if (! $dry) {
            NavigationData::flush();
        }

        $this->info("{$tag}Done." . ($dry ? '' : ' Now write descriptions for the surviving categories in /admin/categories.'));

        return self::SUCCESS;
    }
}
