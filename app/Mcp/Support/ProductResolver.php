<?php

namespace App\Mcp\Support;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves MCP tool inputs to tracked products. Agents usually hold an ASIN or
 * a product name — never our internal IDs — so resolution is: ASIN exact →
 * published review slug exact → name LIKE. Only products with a PUBLISHED
 * review are resolvable: the MCP surface exposes nothing that isn't already
 * public on the site.
 */
class ProductResolver
{
    public static function byAsin(string $asin): ?Product
    {
        return self::reviewedProducts()
            ->where('asin', strtoupper(trim($asin)))
            ->orderBy('id')
            ->first();
    }

    /**
     * Query resolution: an exact review-slug match wins (agents often hold the
     * review URL), then a name substring match.
     */
    public static function byQuery(string $query): ?Product
    {
        $query = trim($query);

        $bySlug = self::reviewedProducts()
            ->whereHas('posts', fn ($q) => self::reviewScope($q)->where('slug', mb_strtolower($query)))
            ->orderBy('id')
            ->first();

        if ($bySlug) {
            return $bySlug;
        }

        return self::reviewedProducts()
            ->where('name', 'like', '%'.self::escapeLike($query).'%')
            ->orderBy('name')
            ->orderBy('id')
            ->first();
    }

    /**
     * Broad match for the search tool: name or brand substring, or ASIN exact.
     *
     * @return Collection<int, Product>
     */
    public static function search(string $query, int $limit = 10): Collection
    {
        $like = '%'.self::escapeLike(trim($query)).'%';
        $asin = strtoupper(trim($query));

        return self::reviewedProducts()
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('brand', 'like', $like)
                ->orWhere('asin', $asin))
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** The published review a tool should link to (newest wins, like the /deals feed). */
    public static function reviewFor(Product $product): ?Post
    {
        return self::reviewScope($product->posts())
            ->latest('published_at')
            ->select(['posts.id', 'posts.title', 'posts.slug', 'posts.published_at'])
            ->first();
    }

    /**
     * @return Builder<Product>
     */
    private static function reviewedProducts(): Builder
    {
        return Product::query()->whereHas('posts', fn ($q) => self::reviewScope($q));
    }

    /** Published, review-type posts only (tips and news don't anchor a product). */
    private static function reviewScope($query)
    {
        return $query->published()->whereNotIn('type', ['tech_tip', 'tech_news']);
    }

    /** LIKE wildcards in agent input are literals, not patterns. */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
