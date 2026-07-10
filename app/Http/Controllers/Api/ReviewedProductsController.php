<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ReviewedProductsController extends Controller
{
    public function index()
    {
        // post_slug lets the research step suggest ALTERNATIVES with internal
        // links ("How it compares" sections link to existing reviews).
        $products = Product::query()
            ->select('id', 'name', 'asin')
            ->with(['posts' => fn ($q) => $q->published()
                ->whereNotIn('type', ['tech_tip', 'tech_news'])
                ->latest('published_at')
                ->select(['posts.id', 'slug']),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'asin' => $p->asin,
                'post_slug' => $p->posts->first()?->slug,
            ]);

        return response()->json([
            'products' => $products,
            'count' => $products->count(),
        ]);
    }
}
