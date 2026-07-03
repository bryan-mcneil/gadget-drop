<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Support\NavigationData;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Move individual products (and the posts that review them) to the right hub
 * per config('site.category_rehome'), and attach missing categories to posts
 * per config('site.category_rehome_posts'). Companion cleanup to
 * categories:consolidate, whose bulk merge left some items in odd hubs.
 * Idempotent: items already in their target category are skipped.
 *
 *   php artisan categories:rehome --dry-run   # review the moves first
 *   php artisan categories:rehome
 */
class RehomeCategoryItems extends Command
{
    protected $signature = 'categories:rehome {--dry-run : Report what would change without writing}';

    protected $description = 'Re-home individual products/posts per config(site.category_rehome*).';

    public function handle(): int
    {
        $products = config('site.category_rehome', []);
        $posts    = config('site.category_rehome_posts', []);

        if (empty($products) && empty($posts)) {
            $this->error('site.category_rehome and site.category_rehome_posts are empty — nothing to re-home.');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $tag = $dry ? '[dry-run] ' : '';

        foreach ($products as $productName => $targetSlug) {
            $product = Product::where('name', $productName)->first();

            if (! $product) {
                $this->warn("{$tag}skip: product '{$productName}' not found");
                continue;
            }

            $target = $this->resolveTarget($targetSlug, $dry);

            if ($product->category_id === $target?->id) {
                $this->line("{$tag}skip: '{$productName}' already in '{$targetSlug}'");
                continue;
            }

            $oldCategoryId = $product->category_id;

            // Swap the hub only on linked posts that actually carry the
            // product's old category — a post linked for other reasons (e.g.
            // a loose related-product attach) keeps its own categorization.
            $linkedPosts = $oldCategoryId
                ? $product->posts()->whereHas('categories', fn ($q) => $q->whereKey($oldCategoryId))->get()
                : collect();

            $this->info("{$tag}'{$productName}' → '{$targetSlug}': moving product + re-pivoting {$linkedPosts->count()} post(s)");

            if (! $dry) {
                $product->update(['category_id' => $target->id]);

                foreach ($linkedPosts as $post) {
                    $post->categories()->detach($oldCategoryId);
                    $post->categories()->syncWithoutDetaching([$target->id]);
                }
            }
        }

        foreach ($posts as $postSlug => $targetSlug) {
            $post = Post::where('slug', $postSlug)->first();

            if (! $post) {
                $this->warn("{$tag}skip: post '{$postSlug}' not found");
                continue;
            }

            $target = $this->resolveTarget($targetSlug, $dry);

            if ($post->categories()->whereKey($target?->id)->exists()) {
                $this->line("{$tag}skip: post '{$postSlug}' already in '{$targetSlug}'");
                continue;
            }

            $this->info("{$tag}post '{$postSlug}' → attaching '{$targetSlug}'");

            if (! $dry) {
                $post->categories()->syncWithoutDetaching([$target->id]);
            }
        }

        if (! $dry) {
            NavigationData::flush();
        }

        $this->info("{$tag}Done." . ($dry ? '' : ' New categories need a description in /admin/categories.'));

        return self::SUCCESS;
    }

    private function resolveTarget(string $slug, bool $dry): ?Category
    {
        $target = Category::where('slug', $slug)->first();

        if (! $target) {
            $name = config("site.category_renames.{$slug}")
                ?? Str::of($slug)->replace('-', ' ')->title()->toString();
            $this->info(($dry ? '[dry-run] ' : '') . "creating target category '{$slug}' ({$name})");
            $target = $dry
                ? null
                : Category::create(['slug' => $slug, 'name' => $name]);
        }

        return $target;
    }
}
