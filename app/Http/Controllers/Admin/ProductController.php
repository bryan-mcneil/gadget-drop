<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'products' => Product::with('category')
                ->latest()
                ->paginate(20)
                ->through(fn ($p) => [
                    'id'       => $p->id,
                    'name'     => $p->name,
                    'asin'     => $p->asin,
                    'price'    => $p->price,
                    'category' => $p->category?->name,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'brand'               => 'nullable|string|max:100',
            'asin'                => 'nullable|string|max:20',
            'gtin'                => 'nullable|string|max:14|regex:/^\d+$/',
            'affiliate_url'       => 'required|url',
            'image_url'           => 'nullable|url',
            'price'               => 'nullable|numeric|min:0',
            'amazon_rating'       => 'nullable|numeric|min:0|max:5',
            'amazon_review_count' => 'nullable|integer|min:0',
            'description'         => 'nullable|string',
            'category_id'         => 'nullable|exists:categories,id',
        ]);

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Product added.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product'    => $product,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'brand'               => 'nullable|string|max:100',
            'asin'                => 'nullable|string|max:20',
            'gtin'                => 'nullable|string|max:14|regex:/^\d+$/',
            'affiliate_url'       => 'required|url',
            'image_url'           => 'nullable|url',
            'price'               => 'nullable|numeric|min:0',
            'amazon_rating'       => 'nullable|numeric|min:0|max:5',
            'amazon_review_count' => 'nullable|integer|min:0',
            'description'         => 'nullable|string',
            'category_id'         => 'nullable|exists:categories,id',
        ]);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }
}
