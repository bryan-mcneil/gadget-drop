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
                    ->get(['id', 'name', 'slug'])
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]),
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
                    ->get(['id', 'title', 'slug', 'published_at'])
                    ->map(fn ($p) => [
                        'id'           => $p->id,
                        'title'        => $p->title,
                        'slug'         => $p->slug,
                        'published_at' => $p->published_at?->format('Y-m-d'),
                    ]),
                'latestNews' => Post::published()
                    ->where('type', 'tech_news')
                    ->latest('published_at')
                    ->take(6)
                    ->get(['id', 'title', 'slug', 'published_at'])
                    ->map(fn ($p) => [
                        'id'           => $p->id,
                        'title'        => $p->title,
                        'slug'         => $p->slug,
                        'published_at' => $p->published_at?->format('Y-m-d'),
                    ]),
            ],
        ];
    }
}
