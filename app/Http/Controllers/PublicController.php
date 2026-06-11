<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\AmazonProductService;
use App\Support\ArticleBody;
use App\Support\NavigationData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home(): View
    {
        $slides  = [];
        $seenIds = [];

        $fmt = fn ($p) => [
            'id'      => $p->id,
            'type'    => $p->type,
            'title'   => $p->title,
            'slug'    => $p->slug,
            'excerpt' => $p->excerpt,
            'featured_image'          => $p->featured_image,
            'featured_image_position' => $p->featured_image_position ?? 'center center',
            // hero_image is the wide-crop override for carousel/banner contexts
            'hero_image'              => $p->hero_image ?: $p->featured_image,
            'hero_image_position'     => $p->hero_image
                ? ($p->hero_image_position ?? 'center center')
                : ($p->featured_image_position ?? 'center center'),
            'published_at' => $p->published_at?->format('Y-m-d'),
        ];

        $cols = ['id', 'type', 'title', 'slug', 'excerpt', 'featured_image', 'featured_image_position', 'hero_image', 'hero_image_position', 'published_at'];

        // Slide 1 — Today's Drop (articles only)
        $today = Post::published()->whereNotIn('type', ['tech_tip', 'tech_news'])->latest('published_at')->first($cols);
        if ($today) {
            $seenIds[] = $today->id;
            $slides[]  = ['label' => "Today's Drop", 'post' => $fmt($today)];
        }

        // Slide 2 — Yesterday's Drop (articles only)
        $yesterday = Post::published()->whereNotIn('type', ['tech_tip', 'tech_news'])->latest('published_at')
            ->whereNotIn('id', $seenIds)->first($cols);
        if ($yesterday) {
            $seenIds[] = $yesterday->id;
            $slides[]  = ['label' => "Yesterday's Drop", 'post' => $fmt($yesterday)];
        }

        // Slide 3 — Top Trending (most views, articles only)
        $trending = Post::published()->whereNotIn('type', ['tech_tip', 'tech_news'])->orderByDesc('view_count')
            ->whereNotIn('id', $seenIds)->first($cols);
        if ($trending) {
            $seenIds[] = $trending->id;
            $slides[]  = ['label' => 'Top Trending', 'post' => $fmt($trending)];
        }

        // Slide 4 — Top Featured [Random Tag] Pick (articles only)
        $tag = Tag::whereHas('posts', fn ($q) => $q->published()->whereNotIn('type', ['tech_tip', 'tech_news']))
            ->withCount(['posts' => fn ($q) => $q->published()->whereNotIn('type', ['tech_tip', 'tech_news'])])
            ->inRandomOrder()
            ->first();
        if ($tag) {
            $tagPost = Post::published()
                ->whereNotIn('type', ['tech_tip', 'tech_news'])
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

        // Slide 6 — Latest Tech News
        $latestNewsPost = Post::published()->where('type', 'tech_news')->latest('published_at')->first($cols);
        if ($latestNewsPost) {
            $slides[] = ['label' => 'Latest Tech News', 'post' => $fmt($latestNewsPost)];
        }

        // Top Picks — up to 6 unique products from recent articles (no tech tips or news)
        $topPickPosts = Post::published()
            ->whereNotIn('type', ['tech_tip', 'tech_news'])
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

        // Featured Spotlight — most recent article (not tech tip or news) with at least one product
        $spotlightPost = Post::published()
            ->whereNotIn('type', ['tech_tip', 'tech_news'])
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

        view()->share('serverMeta', [
            'title'       => 'GadgetDrop | Daily Tech Picks, Gadget Reviews & Buying Guides',
            'description' => 'Daily tech picks, gadget reviews, and buying guides. Find the best gear at the best price, delivered fresh every day.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => url('/'),
        ]);

        view()->share('serverJsonLd', $this->buildHomeJsonLd());

        $nav = NavigationData::get();

        return view('public.home', [
            'heroSlides' => $slides,
            'recentPosts' => Post::published()
                ->whereNotIn('type', ['tech_tip', 'tech_news'])
                ->latest('published_at')
                ->skip(1)
                ->take(8)
                ->get($cols)
                ->map($fmt),
            'categories' => Category::whereHas('posts', fn ($q) => $q->published())
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'featured_image' => $c->featured_image, 'posts_count' => $c->posts_count]),
            'spotlight'   => $spotlight,
            'topPicks'    => $topPicks,
            'popularTags' => $nav['popularTags'],
            'latestNews'  => $nav['latestNews'],
            'tools'       => $nav['tools'],
        ]);
    }

    public function news(): View
    {
        $paginator = Post::published()
            ->where('type', 'tech_news')
            ->latest('published_at')
            ->paginate(12)
            ->through(fn ($p) => [
                'id'             => $p->id,
                'title'          => $p->title,
                'slug'           => $p->slug,
                'excerpt'        => $p->excerpt,
                'featured_image'          => $p->featured_image,
                'featured_image_position' => $p->featured_image_position ?? 'center center',
                'source_url'     => $p->source_url,
                'published_at'   => $p->published_at?->format('Y-m-d'),
                'published_at_iso' => $p->published_at?->toIso8601String(),
                'read_minutes'   => max(1, (int) ceil(str_word_count(strip_tags($p->body ?? '')) / 200)),
            ]);

        view()->share('serverMeta', [
            'title'       => 'Tech News | GadgetDrop',
            'description' => 'The latest in tech: breaking stories, product launches, and industry moves.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('news'),
        ]);

        return view('public.news', ['posts' => $paginator]);
    }

    public function show(Post $post): View
    {
        abort_unless($post->status === 'published', 404);

        $post->increment('view_count');
        $post->load(['categories', 'tags', 'products', 'seoMeta', 'user']);

        // Freshen product data from Amazon PA API (cached); image always stays from DB
        $post->products->each(function ($product) {
            $api = $this->resolveApiData($product->asin);
            if ($api) {
                $product->name                = $api['name']                ?? $product->name;
                $product->price               = $api['price']               ?? $product->price;
                $product->description         = $api['description']         ?? $product->description;
                $product->brand               = $api['brand']               ?? null;
                $product->amazon_rating       = $api['amazon_rating']       ?? null;
                $product->amazon_review_count = $api['amazon_review_count'] ?? null;
            }
        });

        $postData = [
            'id'             => $post->id,
            'type'           => $post->type,
            'title'          => $post->title,
            'slug'           => $post->slug,
            'excerpt'        => $post->excerpt,
            'body'           => $post->body,
            'featured_image'          => $post->featured_image,
            'featured_image_fit'      => $post->featured_image_fit ?? 'cover',
            'featured_image_position' => $post->featured_image_position ?? 'center center',
            'hero_image'              => $post->hero_image ?: $post->featured_image,
            'hero_image_position'     => $post->hero_image
                ? ($post->hero_image_position ?? 'center center')
                : ($post->featured_image_position ?? 'center center'),
            'image_1'            => $post->image_1,
            'image_1_fit'        => $post->image_1_fit ?? 'cover',
            'image_2'            => $post->image_2,
            'image_2_fit'        => $post->image_2_fit ?? 'cover',
            'image_3'            => $post->image_3,
            'image_3_fit'        => $post->image_3_fit ?? 'cover',
            'source_url'     => $post->source_url,
            'published_at'     => $post->published_at?->format('Y-m-d'),
            'published_at_iso' => $post->published_at?->toIso8601String(),
            'categories'     => $post->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]),
            'tags'           => $post->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]),
            'products'       => $post->products->map(fn ($p) => [
                'id'                  => $p->id,
                'name'                => $p->name,
                'description'         => $p->description,
                'price'               => $p->price,
                'image_url'           => $p->image_url,
                'brand'               => $p->brand,
                'gtin'                => $p->gtin,
                'amazon_rating'       => $p->amazon_rating,
                'amazon_review_count' => $p->amazon_review_count,
            ]),
            'seo_meta' => $post->seoMeta ? [
                'meta_title'       => $post->seoMeta->meta_title,
                'meta_description' => $post->seoMeta->meta_description,
                'canonical_url'    => $post->seoMeta->canonical_url,
                'og_image'         => $post->seoMeta->og_image,
                'focus_keyword'    => $post->seoMeta->focus_keyword,
            ] : null,
            'rating' => $post->rating,
            'pros'   => $post->pros ?? [],
            'cons'   => $post->cons ?? [],
            'user' => $post->user ? [
                'id'         => $post->user->id,
                'name'       => $post->user->name,
                'bio'        => $post->user->bio,
                'avatar_url' => $post->user->avatar_url,
                'slug'       => $post->user->slug,
            ] : null,
            'short_url'    => $post->share_code
                ? url('/s/' . $post->share_code)
                : url('/posts/' . $post->slug),
            'read_minutes' => max(1, (int) ceil(str_word_count(strip_tags($post->body ?? '')) / 200)),
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

        // categoryPosts — same type only so tips/news don't surface product articles
        $categoryPostsQuery = Post::published()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->take(8);

        if (in_array($post->type, ['tech_tip', 'tech_news'])) {
            $categoryPostsQuery->where('type', $post->type);
        } else {
            $categoryPostsQuery->whereNotIn('type', ['tech_tip', 'tech_news']);
        }

        $categoryPosts = $categoryPostsQuery->get($cols)->map($formatPost);

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

        $recentQuery = Post::published()
            ->whereNotIn('id', $excludeIds)
            ->latest('published_at')
            ->take(15);

        if ($post->type === 'tech_news') {
            $recentQuery->where('type', 'tech_news');
        } else {
            $recentQuery->whereNotIn('type', ['tech_tip', 'tech_news']);
        }

        $recentPosts = $recentQuery->get($cols)->map($formatPost);

        // Related products for tips and news — pulled from shared categories
        $relatedProducts = [];
        if (in_array($post->type, ['tech_tip', 'tech_news']) && $categoryIds->isNotEmpty()) {
            $relatedProducts = Product::whereIn('category_id', $categoryIds)
                ->latest()
                ->take(4)
                ->get(['id', 'name', 'price', 'image_url', 'affiliate_url'])
                ->map(fn ($p) => [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'price'     => $p->price,
                    'image_url' => $p->image_url,
                ])
                ->all();
        }

        // Share with blade for server-side injection (visible to Googlebot on first crawl)
        view()->share('serverMeta',   $this->buildServerMeta($postData));
        view()->share('serverJsonLd', $this->buildServerJsonLd($postData));

        // Server-side Markdown: body split into thirds with inline images injected
        $sections = ArticleBody::sections(
            $post->body,
            [$post->image_1, $post->image_2, $post->image_3],
            [$post->image_1_fit ?? 'cover', $post->image_2_fit ?? 'cover', $post->image_3_fit ?? 'cover'],
        );

        return view('public.show', [
            'post'             => $postData,
            'sections'         => $sections,
            'categoryPosts'    => $categoryPosts,
            'tagPosts'         => $tagPosts,
            'recentPosts'      => $recentPosts,
            'relatedProducts'  => $relatedProducts,
        ]);
    }

    private function buildHomeJsonLd(): string
    {
        $base = url('');

        $graph = [
            [
                '@type'        => 'Organization',
                '@id'          => "{$base}/#organization",
                'name'          => 'GadgetDrop',
                'alternateName' => ['GDT', 'Tech Drop', 'GadgetDrop Tech'],
                'url'          => $base,
                'logo'         => ['@type' => 'ImageObject', 'url' => "{$base}/favicon.svg"],
                'description'  => 'GadgetDrop is a daily tech picks and gadget review site covering consumer electronics available on Amazon.',
                'contactPoint' => ['@type' => 'ContactPoint', 'email' => 'hello@gadgetdrop.tech', 'contactType' => 'customer service'],
            ],
            [
                '@type'         => 'WebSite',
                '@id'           => "{$base}/#website",
                'name'          => 'GadgetDrop',
                'alternateName' => ['GDT', 'Tech Drop', 'GadgetDrop Tech'],
                'url'           => $base,
                'publisher' => ['@id' => "{$base}/#organization"],
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => "{$base}/search?q={search_term_string}"],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function buildServerMeta(array $d): array
    {
        $seo = $d['seo_meta'] ?? [];
        return [
            'title'       => ($seo['meta_title']       ?? null) ?: "{$d['title']} | GadgetDrop",
            'description' => ($seo['meta_description'] ?? null) ?: ($d['excerpt'] ?? ''),
            // Branded, auto-generated 1200×630 share card — falls back to a manual SEO override if set.
            'og_image'    => ($seo['og_image']          ?? null) ?: route('og.posts.show', $d['slug']),
            'og_image_alt' => $d['title'],
            'og_type'     => 'article',
            'canonical'   => ($seo['canonical_url']     ?? null) ?: (url("/posts/{$d['slug']}")),
        ];
    }

    private function buildServerJsonLd(array $d): string
    {
        $base    = url('');
        $seo     = $d['seo_meta'] ?? [];
        $postUrl = ($seo['canonical_url'] ?? null) ?: "{$base}/posts/{$d['slug']}";

        $amazonShipping = [
            '@type'                => 'OfferShippingDetails',
            'shippingRate'         => ['@type' => 'MonetaryAmount', 'value' => '0', 'currency' => 'USD'],
            'shippingDestination'  => ['@type' => 'DefinedRegion', 'addressCountry' => 'US'],
            'deliveryTime'         => [
                '@type'       => 'ShippingDeliveryTime',
                'handlingTime'=> ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 2, 'maxValue' => 5, 'unitCode' => 'DAY'],
            ],
        ];

        $amazonReturnPolicy = [
            '@type'                => 'MerchantReturnPolicy',
            'applicableCountry'    => 'US',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays'   => 30,
            'returnMethod'         => 'https://schema.org/ReturnByMail',
            'returnFees'           => 'https://schema.org/FreeReturn',
        ];

        $graph = [];

        // BlogPosting
        $article = [
            '@type'            => 'BlogPosting',
            '@id'              => "{$postUrl}#article",
            'headline'         => $d['title'],
            'url'              => $postUrl,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $postUrl],
            'datePublished'    => $d['published_at_iso'] ?? null,
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => 'GadgetDrop',
                'logo'  => ['@type' => 'ImageObject', 'url' => "{$base}/favicon.svg"],
            ],
        ];

        $desc = ($seo['meta_description'] ?? null) ?: ($d['excerpt'] ?? null);
        if ($desc) $article['description'] = $desc;

        $img = ($seo['og_image'] ?? null) ?: ($d['featured_image'] ?? null);
        if ($img) $article['image'] = ['@type' => 'ImageObject', 'url' => $img];

        if ($d['user']['name'] ?? null) {
            $article['author'] = [
                '@type' => 'Person',
                'name'  => $d['user']['name'],
                'url'   => "{$base}/author/{$d['user']['slug']}",
            ];
        }

        $keywords = collect($d['tags'] ?? [])->pluck('name')->implode(', ');
        if ($keywords) $article['keywords'] = $keywords;

        $graph[] = $article;

        // Products
        foreach (collect($d['products'] ?? []) as $p) {
            $p = (array) $p;

            $product = [
                '@type'                  => 'Product',
                'name'                   => $p['name'],
                'hasMerchantReturnPolicy' => $amazonReturnPolicy,
                'offers' => [
                    '@type'                  => 'Offer',
                    'priceCurrency'          => 'USD',
                    'availability'           => 'https://schema.org/InStock',
                    'itemCondition'          => 'https://schema.org/NewCondition',
                    'url'                    => "{$base}/out/{$p['id']}",
                    'seller'                 => ['@type' => 'Organization', 'name' => 'Amazon'],
                    'shippingDetails'        => $amazonShipping,
                    'hasMerchantReturnPolicy' => $amazonReturnPolicy,
                ],
            ];

            if ($p['brand'] ?? null)       $product['brand']       = ['@type' => 'Brand', 'name' => $p['brand']];
            if ($p['description'] ?? null) $product['description'] = $p['description'];
            if ($p['image_url'] ?? null)   $product['image']       = $p['image_url'];
            if (isset($p['price']) && $p['price'] !== null) {
                $product['offers']['price'] = (float) $p['price'];
            }
            if ($p['gtin'] ?? null) {
                $gtin     = preg_replace('/\D/', '', $p['gtin']);
                $gtinProp = match(strlen($gtin)) {
                    8       => 'gtin8',
                    12      => 'gtin12',
                    13      => 'gtin13',
                    14      => 'gtin14',
                    default => 'gtin',
                };
                $product[$gtinProp] = $gtin;
            }

            if (($p['amazon_rating'] ?? null) && ($p['amazon_review_count'] ?? null)) {
                $product['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => $p['amazon_rating'],
                    'reviewCount' => $p['amazon_review_count'],
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ];
            }

            if ($d['rating'] ?? null) {
                $review = [
                    '@type'         => 'Review',
                    'author'        => [
                        '@type' => 'Person',
                        'name'  => $d['user']['name'] ?? 'GadgetDrop Editorial',
                        'url'   => isset($d['user']['slug']) ? "{$base}/author/{$d['user']['slug']}" : null,
                    ],
                    'datePublished' => $d['published_at_iso'] ?? null,
                    'reviewRating'  => [
                        '@type'       => 'Rating',
                        'ratingValue' => (float) $d['rating'],
                        'bestRating'  => 5,
                        'worstRating' => 1,
                    ],
                ];

                $pros = $d['pros'] ?? [];
                if (!empty($pros)) {
                    $review['positiveNotes'] = ['@type' => 'ItemList', 'itemListElement' =>
                        array_values(array_map(fn ($v, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $v],
                            $pros, array_keys($pros)))];
                }

                $cons = $d['cons'] ?? [];
                if (!empty($cons)) {
                    $review['negativeNotes'] = ['@type' => 'ItemList', 'itemListElement' =>
                        array_values(array_map(fn ($v, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $v],
                            $cons, array_keys($cons)))];
                }

                $product['review'] = $review;
            }

            $graph[] = $product;
        }

        // BreadcrumbList
        $crumbs   = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base]];
        $firstCat = collect($d['categories'] ?? [])->first();

        if ($firstCat) {
            $firstCat = (array) $firstCat;
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $firstCat['name'], 'item' => "{$base}/category/{$firstCat['slug']}"];
            $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $d['title'], 'item' => $postUrl];
        } else {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $d['title'], 'item' => $postUrl];
        }

        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public function category(Category $category): View
    {
        $categoryData = [
            'id'             => $category->id,
            'name'           => $category->name,
            'slug'           => $category->slug,
            'description'    => $category->description,
            'featured_image' => $category->featured_image,
        ];

        $posts = Post::published()
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
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
            ]);

        $categories = Category::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'featured_image']);

        view()->share('serverMeta', [
            'title'       => "{$category->name} | GadgetDrop",
            'description' => "Browse {$category->name} reviews, picks, and buying guides on GadgetDrop. {$posts->total()} posts and counting.",
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('category', $category->slug),
        ]);

        return view('public.category', [
            'category'   => $categoryData,
            'posts'      => $posts,
            'categories' => $categories,
        ]);
    }

    public function tag(Tag $tag): View
    {
        $tagData = [
            'id'   => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ];

        $posts = Post::published()
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
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
            ]);

        $categories = Category::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'featured_image']);

        view()->share('serverMeta', [
            'title'       => "#{$tag->name} | GadgetDrop",
            'description' => "Browse #{$tag->name} posts on GadgetDrop. {$posts->total()} post" . ($posts->total() !== 1 ? 's' : '') . " tagged.",
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('tag', $tag->slug),
        ]);

        return view('public.tag', [
            'tag'        => $tagData,
            'posts'      => $posts,
            'categories' => $categories,
        ]);
    }

    public function search(Request $request): View
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
                ->whereHas('posts', fn ($q) => $q->published())
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderByDesc('posts_count')
                ->take(6)
                ->get(['id', 'name', 'slug', 'posts_count']);

            $tags = Tag::where('name', 'like', "%{$query}%")
                ->whereHas('posts', fn ($q) => $q->published())
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderByDesc('posts_count')
                ->take(10)
                ->get(['id', 'name', 'slug', 'posts_count']);
        }

        view()->share('serverMeta', [
            'title'       => $query !== '' ? "\"{$query}\" | Search" : 'Search | GadgetDrop',
            'description' => $query !== '' ? "Search results for \"{$query}\" on GadgetDrop." : 'Search GadgetDrop for tech reviews, gadget picks, and buying guides.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('search'),
        ]);

        return view('public.search', [
            'query'       => $query,
            'posts'       => $posts,
            'categories'  => $categories,
            'tags'        => $tags,
            'popularTags' => \App\Support\NavigationData::get()['popularTags'],
        ]);
    }

    public function about(): View
    {
        view()->share('serverMeta', [
            'title'       => 'About GadgetDrop',
            'description' => 'GadgetDrop is a daily tech picks and gadget review site. Learn who we are, how we find the best gear, and how our content is made.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('about'),
        ]);

        return view('public.about');
    }

    public function privacy(): View
    {
        view()->share('serverMeta', [
            'title'       => 'Privacy Policy | GadgetDrop',
            'description' => 'GadgetDrop privacy policy. Learn how we collect, use, and protect your data.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('privacy'),
        ]);

        return view('public.privacy');
    }

    public function contact(): View
    {
        view()->share('serverMeta', [
            'title'       => 'Contact | GadgetDrop',
            'description' => 'Get in touch with the GadgetDrop team. Questions, corrections, partnerships, and press enquiries welcome.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('contact'),
        ]);

        return view('public.contact');
    }

    public function cookies(): View
    {
        view()->share('serverMeta', [
            'title'       => 'Cookie Policy | GadgetDrop',
            'description' => 'GadgetDrop cookie policy. Learn what cookies we use, why, and how to manage your preferences.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('cookies'),
        ]);

        return view('public.cookies');
    }

    public function terms(): View
    {
        view()->share('serverMeta', [
            'title'       => 'Terms of Service | GadgetDrop',
            'description' => 'GadgetDrop terms of service. Read the rules and conditions for using this site.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('terms'),
        ]);

        return view('public.terms');
    }

    public function author(User $user): View
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

        view()->share('serverMeta', [
            'title'       => "{$user->name} | GadgetDrop",
            'description' => $user->bio ?: "Posts by {$user->name} on GadgetDrop.",
            'og_image'    => $user->avatar_url,
            'og_type'     => 'profile',
            'canonical'   => route('author', $user->slug),
        ]);

        return view('public.author', [
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

    public function shortlink(Post $post): RedirectResponse
    {
        return redirect()->route('posts.show', $post->slug, 302);
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
