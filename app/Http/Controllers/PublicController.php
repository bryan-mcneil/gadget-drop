<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\AmazonProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(): Response
    {
        $slides  = [];
        $seenIds = [];

        $fmt = fn ($p) => [
            'id'             => $p->id,
            'type'           => $p->type,
            'title'          => $p->title,
            'slug'           => $p->slug,
            'excerpt'        => $p->excerpt,
            'featured_image' => $p->featured_image,
            'published_at'   => $p->published_at?->format('Y-m-d'),
        ];

        $cols = ['id', 'type', 'title', 'slug', 'excerpt', 'featured_image', 'published_at'];

        // Slide 1 — Today's Drop (articles only)
        $today = Post::published()->where('type', '!=', 'tech_tip')->latest('published_at')->first($cols);
        if ($today) {
            $seenIds[] = $today->id;
            $slides[]  = ['label' => "Today's Drop", 'post' => $fmt($today)];
        }

        // Slide 2 — Yesterday's Drop (articles only)
        $yesterday = Post::published()->where('type', '!=', 'tech_tip')->latest('published_at')
            ->whereNotIn('id', $seenIds)->first($cols);
        if ($yesterday) {
            $seenIds[] = $yesterday->id;
            $slides[]  = ['label' => "Yesterday's Drop", 'post' => $fmt($yesterday)];
        }

        // Slide 3 — Top Trending (most views, articles only)
        $trending = Post::published()->where('type', '!=', 'tech_tip')->orderByDesc('view_count')
            ->whereNotIn('id', $seenIds)->first($cols);
        if ($trending) {
            $seenIds[] = $trending->id;
            $slides[]  = ['label' => 'Top Trending', 'post' => $fmt($trending)];
        }

        // Slide 4 — Top Featured [Random Tag] Pick (articles only)
        $tag = Tag::withCount(['posts' => fn ($q) => $q->published()->where('type', '!=', 'tech_tip')])
            ->having('posts_count', '>', 0)
            ->inRandomOrder()
            ->first();
        if ($tag) {
            $tagPost = Post::published()
                ->where('type', '!=', 'tech_tip')
                ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
                ->whereNotIn('id', $seenIds)
                ->latest('published_at')
                ->first($cols);
            if ($tagPost) {
                $slides[] = ['label' => "Top {$tag->name} Pick", 'post' => $fmt($tagPost)];
            }
        }

        // Slide 5 — Latest Tech Tip
        $latestTechTip = Post::published()->where('type', 'tech_tip')->latest('published_at')->first($cols);
        if ($latestTechTip) {
            $slides[] = ['label' => 'Latest Tech Tip', 'post' => $fmt($latestTechTip)];
        }

        // Top Picks — up to 6 unique products from recent articles (no tech tips)
        $topPickPosts = Post::published()
            ->where('type', '!=', 'tech_tip')
            ->has('products')
            ->with(['products' => fn ($q) => $q->orderBy('display_order')->limit(1)])
            ->latest('published_at')
            ->take(12)
            ->get(['id', 'slug']);

        $seenProductIds = [];
        $topPicks = [];
        foreach ($topPickPosts as $tp) {
            $product = $tp->products->first();
            if ($product && ! in_array($product->id, $seenProductIds)) {
                $seenProductIds[] = $product->id;
                $api = $this->resolveApiData($product->asin);
                $topPicks[] = [
                    'id'        => $product->id,
                    'name'      => $api['name']  ?? $product->name,
                    'price'     => $api['price'] ?? $product->price,
                    'image_url' => $product->image_url,
                    'post_slug' => $tp->slug,
                ];
            }
            if (count($topPicks) >= 6) break;
        }

        // Featured Spotlight — most recent article (not tech tip) with at least one product
        $spotlightPost = Post::published()
            ->where('type', '!=', 'tech_tip')
            ->has('products')
            ->with(['products' => fn ($q) => $q->orderBy('display_order')->limit(1)])
            ->latest('published_at')
            ->first(['id', 'title', 'slug']);

        $spotlight = null;
        if ($spotlightPost && $spotlightPost->products->isNotEmpty()) {
            $sp  = $spotlightPost->products->first();
            $api = $this->resolveApiData($sp->asin);
            $spotlight = [
                'post'    => ['title' => $spotlightPost->title, 'slug' => $spotlightPost->slug],
                'product' => [
                    'id'          => $sp->id,
                    'name'        => $api['name']        ?? $sp->name,
                    'description' => $api['description'] ?? $sp->description,
                    'price'       => $api['price']       ?? $sp->price,
                    'image_url'   => $sp->image_url,
                ],
            ];
        }

        return Inertia::render('Public/Home', [
            'heroSlides' => $slides,
            'recentPosts' => Post::published()
                ->where('type', '!=', 'tech_tip')
                ->with('categories')
                ->latest('published_at')
                ->skip(1)
                ->take(8)
                ->get($cols)
                ->map($fmt),
            'categories' => Category::withCount(['posts' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'featured_image' => $c->featured_image, 'posts_count' => $c->posts_count]),
            'spotlight' => $spotlight,
            'topPicks'  => $topPicks,
        ]);
    }

    public function show(Post $post): Response
    {
        abort_unless($post->status === 'published', 404);

        $post->increment('view_count');
        $post->load(['categories', 'tags', 'products', 'seoMeta', 'user']);

        // Freshen product data from Amazon PA API (cached); image always stays from DB
        $post->products->each(function ($product) {
            $api = $this->resolveApiData($product->asin);
            if ($api) {
                $product->name        = $api['name']        ?? $product->name;
                $product->price       = $api['price']       ?? $product->price;
                $product->description = $api['description'] ?? $product->description;
            }
        });

        $postData = [
            'id'             => $post->id,
            'type'           => $post->type,
            'title'          => $post->title,
            'slug'           => $post->slug,
            'excerpt'        => $post->excerpt,
            'body'           => $post->body,
            'featured_image'     => $post->featured_image,
            'featured_image_fit' => $post->featured_image_fit ?? 'cover',
            'image_1'            => $post->image_1,
            'image_1_fit'        => $post->image_1_fit ?? 'cover',
            'image_2'            => $post->image_2,
            'image_2_fit'        => $post->image_2_fit ?? 'cover',
            'image_3'            => $post->image_3,
            'image_3_fit'        => $post->image_3_fit ?? 'cover',
            'source_url'     => $post->source_url,
            'published_at'   => $post->published_at?->format('Y-m-d'),
            'categories'     => $post->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]),
            'tags'           => $post->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]),
            'products'       => $post->products->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'price'       => $p->price,
                'image_url'   => $p->image_url,
            ]),
            'seo_meta' => $post->seoMeta ? [
                'meta_title'       => $post->seoMeta->meta_title,
                'meta_description' => $post->seoMeta->meta_description,
                'canonical_url'    => $post->seoMeta->canonical_url,
                'og_image'         => $post->seoMeta->og_image,
                'focus_keyword'    => $post->seoMeta->focus_keyword,
            ] : null,
            'user' => $post->user ? [
                'id'         => $post->user->id,
                'name'       => $post->user->name,
                'bio'        => $post->user->bio,
                'avatar_url' => $post->user->avatar_url,
                'slug'       => $post->user->slug,
            ] : null,
        ];

        $categoryIds = $post->categories->pluck('id');
        $tagIds      = $post->tags->pluck('id');

        $formatPost = fn ($p) => [
            'id'             => $p->id,
            'title'          => $p->title,
            'slug'           => $p->slug,
            'published_at'   => $p->published_at?->format('Y-m-d'),
            'featured_image' => $p->featured_image,
        ];

        $cols = ['id', 'title', 'slug', 'published_at', 'featured_image'];

        $categoryPosts = Post::published()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->take(8)
            ->get($cols)
            ->map($formatPost);

        $excludeIds = $categoryPosts->pluck('id')->push($post->id);

        $tagPosts = $tagIds->isNotEmpty()
            ? Post::published()
                ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
                ->whereNotIn('id', $excludeIds)
                ->latest('published_at')
                ->take(8)
                ->get($cols)
                ->map($formatPost)
            : collect();

        $excludeIds = $excludeIds->merge($tagPosts->pluck('id'));

        $recentPosts = Post::published()
            ->where('type', '!=', 'tech_tip')
            ->whereNotIn('id', $excludeIds)
            ->latest('published_at')
            ->take(15)
            ->get($cols)
            ->map($formatPost);

        return Inertia::render('Public/Post', [
            'post'          => $postData,
            'categoryPosts' => $categoryPosts,
            'tagPosts'      => $tagPosts,
            'recentPosts'   => $recentPosts,
        ]);
    }

    public function category(Category $category): Response
    {
        return Inertia::render('Public/Category', [
            'category' => [
                'id'             => $category->id,
                'name'           => $category->name,
                'slug'           => $category->slug,
                'description'    => $category->description,
                'featured_image' => $category->featured_image,
            ],
            'posts' => Post::published()
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
                ->with('categories')
                ->latest('published_at')
                ->paginate(12)
                ->through(fn ($p) => [
                    'id'             => $p->id,
                    'type'           => $p->type,
                    'title'          => $p->title,
                    'slug'           => $p->slug,
                    'excerpt'        => $p->excerpt,
                    'featured_image' => $p->featured_image,
                    'published_at'   => $p->published_at?->toDateString(),
                ]),
            'categories' => Category::withCount(['posts' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'featured_image']),
        ]);
    }

    public function search(Request $request): Response
    {
        $query = trim($request->get('q', ''));

        $posts      = collect();
        $categories = collect();
        $tags       = collect();

        if (strlen($query) >= 2) {
            $posts = Post::published()
                ->where(fn ($q) => $q
                    ->where('title', 'like', "%{$query}%")
                    ->orWhere('excerpt', 'like', "%{$query}%")
                )
                ->latest('published_at')
                ->take(12)
                ->get(['id', 'type', 'title', 'slug', 'excerpt', 'featured_image', 'published_at'])
                ->map(fn ($p) => [
                    'id'             => $p->id,
                    'type'           => $p->type,
                    'title'          => $p->title,
                    'slug'           => $p->slug,
                    'excerpt'        => $p->excerpt,
                    'featured_image' => $p->featured_image,
                    'published_at'   => $p->published_at?->format('Y-m-d'),
                ]);

            $categories = Category::where('name', 'like', "%{$query}%")
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->orderByDesc('posts_count')
                ->take(6)
                ->get(['id', 'name', 'slug', 'posts_count']);

            $tags = Tag::where('name', 'like', "%{$query}%")
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->having('posts_count', '>', 0)
                ->orderByDesc('posts_count')
                ->take(10)
                ->get(['id', 'name', 'slug', 'posts_count']);
        }

        return Inertia::render('Public/Search', [
            'query'      => $query,
            'posts'      => $posts,
            'categories' => $categories,
            'tags'       => $tags,
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Public/About');
    }

    public function privacy(): Response
    {
        return Inertia::render('Public/Privacy');
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact');
    }

    public function cookies(): Response
    {
        return Inertia::render('Public/Cookies');
    }

    public function author(User $user): Response
    {
        abort_if($user->id === 1, 404);
        $cols = ['id', 'type', 'title', 'slug', 'excerpt', 'featured_image', 'published_at', 'view_count'];

        $posts = Post::published()
            ->where('user_id', $user->id)
            ->latest('published_at')
            ->get($cols)
            ->map(fn ($p) => [
                'id'             => $p->id,
                'type'           => $p->type,
                'title'          => $p->title,
                'slug'           => $p->slug,
                'excerpt'        => $p->excerpt,
                'featured_image' => $p->featured_image,
                'published_at'   => $p->published_at?->format('M j, Y'),
                'view_count'     => $p->view_count,
            ]);

        $totalViews = $posts->sum('view_count');
        $firstPost  = $posts->last(); // oldest is last after latest() sort

        return Inertia::render('Public/Author', [
            'author' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'bio'        => $user->bio,
                'avatar_url' => $user->avatar_url,
                'since'      => $firstPost ? $firstPost['published_at'] : null,
            ],
            'posts'       => $posts,
            'totalViews'  => $totalViews,
            'postCount'   => $posts->count(),
        ]);
    }

    public function redirect(Product $product, Request $request): RedirectResponse
    {
        AffiliateClick::create([
            'product_id' => $product->id,
            'post_id'    => $request->query('post'),
            'ip_hash'    => hash('sha256', $request->ip()),
            'referrer'   => $request->header('referer'),
            'user_agent' => $request->userAgent(),
        ]);

        $url = $this->appendAffiliateTag($product->affiliate_url);

        return redirect()->away($url);
    }

    /**
     * Try to get fresh product data from the Amazon PA API (cached 12 h).
     * Returns null instantly when the API is not configured or the call fails.
     */
    private function resolveApiData(?string $asin): ?array
    {
        if (! $asin) {
            return null;
        }

        return app(AmazonProductService::class)->cachedLookup($asin);
    }

    private function appendAffiliateTag(string $url): string
    {
        $tag = config('services.amazon.affiliate_tag');

        if (! $tag) {
            return $url;
        }

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);
        $params['tag'] = $tag;

        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '');

        return $base . '?' . http_build_query($params);
    }
}
