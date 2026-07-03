<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The daily price-confirmation screen: products listed stalest-first so a
 * 5-minute pass keeps "Price checked" labels honest. Saving a new price lets
 * ProductObserver snapshot it; "confirm unchanged" only refreshes the
 * checked-at stamp.
 */
class PriceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Prices/Index', [
            'products' => Product::query()
                ->whereHas('posts', fn ($q) => $q->published())
                // Never-checked first, then oldest check first — portable across
                // MySQL and the sqlite test schema.
                ->orderByRaw('price_checked_at IS NOT NULL')
                ->orderBy('price_checked_at')
                ->paginate(30)
                ->through(fn ($p) => [
                    'id'               => $p->id,
                    'name'             => $p->name,
                    'asin'             => $p->asin,
                    'price'            => $p->price,
                    'price_checked_at' => $p->price_checked_at?->toIso8601String(),
                    'days_stale'       => $p->price_checked_at
                        ? (int) $p->price_checked_at->diffInDays(now())
                        : null,
                    'snapshots'        => $p->priceSnapshots()->count(),
                ]),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:0|max:99999',
        ]);

        // Same price re-submitted counts as a check, not a change.
        if ((float) $product->price === (float) $data['price']) {
            $product->forceFill(['price_checked_at' => now()])->save();
        } else {
            $product->update(['price' => $data['price']]);
        }

        return back()->with('success', "{$product->name}: price confirmed at \${$data['price']}.");
    }

    public function confirm(Product $product): RedirectResponse
    {
        $product->forceFill(['price_checked_at' => now()])->save();

        return back()->with('success', "{$product->name}: price confirmed unchanged.");
    }
}
