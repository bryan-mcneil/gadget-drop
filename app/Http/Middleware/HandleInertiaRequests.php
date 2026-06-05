<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'   => $request->user()->id,
                    'name' => $request->user()->name,
                ] : null,
            ],
            'navigation' => fn () => [
                'categories' => Category::withCount(['posts' => fn ($q) => $q->published()])
                    ->having('posts_count', '>', 0)
                    ->orderByDesc('posts_count')
                    ->take(12)
                    ->get()
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'posts_count' => $c->posts_count]),
                'popularTags' => Tag::withCount(['posts' => fn ($q) => $q->published()])
                    ->having('posts_count', '>', 0)
                    ->orderByDesc('posts_count')
                    ->take(24)
                    ->get()
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug, 'posts_count' => $t->posts_count]),
                'trending' => Post::published()
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
                    ]),
                'latestTechTips' => Post::published()
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
                    ]),
                'tools' => collect(config('tools'))->map(fn ($tool, $slug) => [
                    'slug'        => $slug,
                    'name'        => $tool['name'],
                    'description' => $tool['description'],
                ])->values(),
                'latestNews' => Post::published()
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
                    ]),
            ],
        ];
    }
}
