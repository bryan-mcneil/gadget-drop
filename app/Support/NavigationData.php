<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for the global navigation data (header megamenus,
 * footer, sidebars). Consumed by both the Inertia middleware (admin) and the
 * public Blade layout via a View Composer.
 *
 * Each section is cached (database store) so the ~5 aggregation queries that
 * ran on every public request collapse to cache reads. The per-request static
 * memo still prevents the layout composer and controllers from double-reading
 * within a single request. Caches are flushed on post save/delete via the Post
 * model (see {@see self::flush()}).
 */
class NavigationData
{
    /** Per-request memo so the layout composer and controllers don't double-query. */
    private static ?array $cache = null;

    /** Post-derived cache keys dropped by flush() when a post changes. */
    private const KEYS = [
        'nav.categories',
        'nav.popularTags',
        'nav.trending',
        'nav.latestTechTips',
        'nav.latestNews',
        'sitemap.xml',
        'search.llms_txt',
    ];

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        return self::$cache = [
            'categories' => Cache::remember('nav.categories', now()->addHour(), fn () =>
                Category::whereHas('posts', fn ($q) => $q->published())
                    ->withCount(['posts' => fn ($q) => $q->published()])
                    ->orderByDesc('posts_count')
                    ->take(12)
                    ->get()
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'posts_count' => $c->posts_count])
                    ->all()),
            'popularTags' => Cache::remember('nav.popularTags', now()->addHour(), fn () =>
                Tag::whereHas('posts', fn ($q) => $q->published())
                    ->withCount(['posts' => fn ($q) => $q->published()])
                    ->orderByDesc('posts_count')
                    ->take(24)
                    ->get()
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'posts_count' => $t->posts_count])
                    ->all()),
            'trending' => Cache::remember('nav.trending', now()->addMinutes(15), fn () =>
                Post::published()
                    ->whereNotIn('type', ['tech_tip', 'tech_news'])
                    ->orderByDesc('view_count')
                    ->latest('published_at')
                    ->take(6)
                    ->get(['id', 'title', 'slug', 'featured_image', 'published_at'])
                    ->map(fn ($p) => [
                        'id'             => $p->id,
                        'title'          => $p->title,
                        'slug'           => $p->slug,
                        'featured_image' => $p->featured_image,
                        'published_at'   => $p->published_at?->format('Y-m-d'),
                    ])
                    ->all()),
            'latestTechTips' => Cache::remember('nav.latestTechTips', now()->addMinutes(15), fn () =>
                Post::published()
                    ->where('type', 'tech_tip')
                    ->latest('published_at')
                    ->take(6)
                    ->get(['id', 'title', 'slug', 'excerpt', 'published_at'])
                    ->map(fn ($p) => [
                        'id'           => $p->id,
                        'title'        => $p->title,
                        'slug'         => $p->slug,
                        'excerpt'      => $p->excerpt,
                        'published_at' => $p->published_at?->format('Y-m-d'),
                    ])
                    ->all()),
            'tools' => collect(config('tools'))->map(fn ($tool, $slug) => [
                'slug'        => $slug,
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'icon'        => $tool['icon'] ?? 'wrench',
            ])->values()->all(),
            'latestNews' => Cache::remember('nav.latestNews', now()->addMinutes(5), fn () =>
                Post::published()
                    ->where('type', 'tech_news')
                    ->latest('published_at')
                    ->take(6)
                    ->get(['id', 'title', 'slug', 'excerpt', 'featured_image', 'featured_image_position', 'hero_image', 'hero_image_position', 'source_url', 'published_at'])
                    ->map(fn ($p) => [
                        'id'      => $p->id,
                        'title'   => $p->title,
                        'slug'    => $p->slug,
                        'excerpt' => $p->excerpt,
                        'featured_image'          => $p->featured_image,
                        'featured_image_position' => $p->featured_image_position ?? 'center center',
                        'hero_image'              => $p->hero_image ?: $p->featured_image,
                        'hero_image_position'     => $p->hero_image
                            ? ($p->hero_image_position ?? 'center center')
                            : ($p->featured_image_position ?? 'center center'),
                        'source_url'       => $p->source_url,
                        'published_at'     => $p->published_at?->format('Y-m-d'),
                        'published_at_iso' => $p->published_at?->toIso8601String(),
                    ])
                    ->all()),
        ];
    }

    /**
     * Drop every cached navigation section and the per-request memo.
     * Called when a post is created, updated, or deleted so the nav never
     * serves stale categories/tags/trending/news.
     */
    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }

        self::$cache = null;
    }
}
