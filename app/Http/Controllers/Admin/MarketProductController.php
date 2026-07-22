<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * View/curate the market layer (docs/MARKET-IMPORT.md). Rows are created by
 * market:import only, so there is no create/destroy — just index + edit for
 * the fields the import can't provide (image) or that need cleanup (category,
 * title/description/brand). Import-owned numbers (prices, rating, seen dates)
 * are read-only here so the change-only snapshot invariant can't be broken
 * from the admin.
 */
class MarketProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => (string) $request->query('category', ''),
        ];

        return Inertia::render('Admin/MarketProducts/Index', [
            'products' => MarketProduct::query()
                ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('title', 'like', '%'.$filters['search'].'%')
                    ->orWhere('brand', 'like', '%'.$filters['search'].'%')
                    ->orWhere('asin', strtoupper($filters['search']))))
                ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
                ->orderByDesc('last_seen_at')
                ->paginate(25)
                ->withQueryString()
                ->through(fn ($p) => [
                    'id' => $p->id,
                    'asin' => $p->asin,
                    'title' => $p->title,
                    'brand' => $p->brand,
                    'category' => $p->category,
                    'image_url' => $p->image_url,
                    'current_price' => $p->current_price,
                    'last_seen_at' => $p->last_seen_at->format('M j, Y'),
                ]),
            'categories' => $this->categories(),
            'filters' => $filters,
        ]);
    }

    public function edit(MarketProduct $marketProduct): Response
    {
        return Inertia::render('Admin/MarketProducts/Edit', [
            'product' => [
                'id' => $marketProduct->id,
                'asin' => $marketProduct->asin,
                'title' => $marketProduct->title,
                'description' => $marketProduct->description,
                'brand' => $marketProduct->brand,
                'category' => $marketProduct->category,
                'url' => $marketProduct->url,
                'image_url' => $marketProduct->image_url,
                'current_price' => $marketProduct->current_price,
                'list_price' => $marketProduct->list_price,
                'rating' => $marketProduct->rating,
                'review_count' => $marketProduct->review_count,
                'first_seen_at' => $marketProduct->first_seen_at->format('M j, Y'),
                'last_seen_at' => $marketProduct->last_seen_at->format('M j, Y'),
            ],
            'snapshots' => $marketProduct->snapshots()
                ->latest('created_at')
                ->limit(12)
                ->get()
                ->map(fn ($s) => [
                    'price' => $s->price,
                    'date' => $s->created_at->format('M j, Y'),
                ]),
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, MarketProduct $marketProduct): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:500',
            'brand' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'image_url' => 'nullable|url|max:500',
        ]);

        $marketProduct->update($data);

        return redirect()
            ->route('admin.market-products.index')
            ->with('success', 'Market product updated.');
    }

    private function categories(): \Illuminate\Support\Collection
    {
        return MarketProduct::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }
}
