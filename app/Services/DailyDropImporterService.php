<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

class DailyDropImporterService
{
    /**
     * Parse pasted text into an array of post data arrays.
     * Accepts a single JSON object or a JSON array of objects.
     */
    public function parseJson(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```\s*$/m', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: '.json_last_error_msg());
        }

        // Wrap single object in an array
        if (isset($decoded['title'])) {
            $decoded = [$decoded];
        }

        if (! is_array($decoded) || empty($decoded)) {
            throw new \Exception('JSON must be a post object or an array of post objects.');
        }

        foreach ($decoded as $i => $item) {
            if (! isset($item['title'], $item['body'])) {
                throw new \Exception('Item #'.($i + 1).' is missing required fields: title and body.');
            }
        }

        return $decoded;
    }

    /**
     * Import an array of parsed post data as draft posts.
     * Returns an array of ['id' => ..., 'title' => ...] for each created post.
     */
    public function importAll(array $posts, int $userId): array
    {
        $created = [];

        foreach ($posts as $data) {
            $post = $this->importOne($data, $userId);
            $created[] = ['id' => $post->id, 'title' => $post->title];
        }

        return $created;
    }

    public const TYPES = ['article', 'tech_tip', 'tech_news'];

    public function importOne(array $data, int $userId): Post
    {
        $type = $data['type'] ?? 'article';
        if (! in_array($type, self::TYPES, true)) {
            throw new \Exception("Unknown post type \"{$type}\" — expected one of: ".implode(', ', self::TYPES).'.');
        }
        $isReview = $type === 'article';

        $slug = $this->slugify($data['title']);
        $baseSlug = $slug;
        $i = 1;

        while (Post::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        // Resolve author by name, fall back to current user
        $authorId = $userId;
        if (! empty($data['author_name'])) {
            $author = User::whereRaw('LOWER(name) = ?', [strtolower($data['author_name'])])->first();
            if ($author) {
                $authorId = $author->id;
            }
        }

        // Resolve category (find or create). Retired slugs are mapped to their
        // consolidated hub first (config site.category_map) so imports can't
        // resurrect a category that categories:consolidate merged away.
        $categoryId = null;
        if (! empty($data['category_name'])) {
            $categorySlug = $this->slugify($data['category_name']);
            $categorySlug = config("site.category_map.{$categorySlug}", $categorySlug);

            $category = Category::firstOrCreate(
                ['slug' => $categorySlug],
                ['name' => config("site.category_renames.{$categorySlug}")
                    ?? ($categorySlug === $this->slugify($data['category_name'])
                        ? $data['category_name']
                        : Str::of($categorySlug)->replace('-', ' ')->title()->toString())],
            );
            $categoryId = $category->id;
        }

        // Resolve tags (find or create)
        $tagIds = collect($data['tag_names'] ?? [])
            ->map(fn ($name) => Tag::firstOrCreate(
                ['slug' => $this->slugify($name)],
                ['name' => ucwords($name)],
            ))
            ->pluck('id')
            ->toArray();

        $post = Post::create([
            'type' => $type,
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'status' => 'draft',
            'user_id' => $authorId,
            'source_url' => $data['source_url'] ?? null,
            'rating' => $isReview ? ($data['rating'] ?? null) : null,
            'pros' => $isReview && ! empty($data['pros']) ? $data['pros'] : null,
            'cons' => $isReview && ! empty($data['cons']) ? $data['cons'] : null,
        ]);

        // Sync category
        if ($categoryId) {
            $post->categories()->sync([$categoryId]);
        }

        // Sync tags
        if ($tagIds) {
            $post->tags()->sync($tagIds);
        }

        // Attach product by ASIN (reviews only)
        if ($isReview && ! empty($data['product_asin'])) {
            $product = Product::where('asin', $data['product_asin'])->first();
            if ($product) {
                $post->products()->sync([$product->id => ['display_order' => 0]]);
            }
        }

        // Create SEO meta
        $seo = $data['seo'] ?? [];
        $targetQuery = $seo['target_query'] ?? $data['target_query'] ?? null;
        $post->seoMeta()->create([
            'meta_title' => $seo['meta_title'] ?? $data['title'],
            'meta_description' => $seo['meta_description'] ?? $data['excerpt'] ?? '',
            'focus_keyword' => $seo['focus_keyword'] ?? '',
            'target_query' => ($targetQuery ?? '') !== '' ? $targetQuery : null,
        ]);

        return $post;
    }

    private function slugify(string $str): string
    {
        return Str::slug($str);
    }
}
