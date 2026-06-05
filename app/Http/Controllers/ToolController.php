<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class ToolController extends Controller
{
    public function index()
    {
        $tools = collect(config('tools'))->map(fn ($tool, $slug) => [
            'slug'        => $slug,
            'name'        => $tool['name'],
            'description' => $tool['description'],
            'icon'        => $tool['icon'],
            'category'    => $tool['category'] ?? null,
        ])->values();

        return Inertia::render('Public/Tools/Index', [
            'tools' => $tools,
        ]);
    }

    public function jsonValidator()
    {
        $tool = config('tools.json-validator');

        return Inertia::render('Public/Tools/JsonValidator', [
            'sidebarProducts' => $this->getSidebarProducts($tool['related_tags']),
            'metaTitle'       => $tool['meta_title'],
            'metaDescription' => $tool['meta_description'],
        ]);
    }

    public function jsCssMinifier()
    {
        $tool = config('tools.js-css-minifier');

        return Inertia::render('Public/Tools/JsCssMinifier', [
            'sidebarProducts' => $this->getSidebarProducts($tool['related_tags']),
            'metaTitle'       => $tool['meta_title'],
            'metaDescription' => $tool['meta_description'],
        ]);
    }

    public function backgroundRemover()
    {
        $tool = config('tools.background-remover');

        return Inertia::render('Public/Tools/BackgroundRemover', [
            'sidebarProducts' => $this->getSidebarProducts($tool['related_tags']),
            'metaTitle'       => $tool['meta_title'],
            'metaDescription' => $tool['meta_description'],
        ]);
    }

    public function imageConverter()
    {
        $tool = config('tools.image-converter');

        return Inertia::render('Public/Tools/ImageConverter', [
            'sidebarProducts' => $this->getSidebarProducts($tool['related_tags']),
            'metaTitle'       => $tool['meta_title'],
            'metaDescription' => $tool['meta_description'],
        ]);
    }

    public function imageCropper()
    {
        $tool = config('tools.image-cropper');

        return Inertia::render('Public/Tools/ImageCropper', [
            'sidebarProducts' => $this->getSidebarProducts($tool['related_tags']),
            'metaTitle'       => $tool['meta_title'],
            'metaDescription' => $tool['meta_description'],
        ]);
    }

    private function getSidebarProducts(array $relatedTags): Collection
    {
        $products = Post::published()
            ->whereHas('tags', fn ($q) => $q->whereIn('slug', $relatedTags))
            ->with(['products' => fn ($q) => $q->select('products.id', 'name', 'asin', 'image_url', 'price', 'description')])
            ->limit(6)
            ->get()
            ->flatMap(fn ($p) => $p->products)
            ->unique('id')
            ->take(4)
            ->values();

        if ($products->isEmpty()) {
            $products = Product::query()
                ->select('id', 'name', 'asin', 'image_url', 'price', 'description')
                ->latest()
                ->limit(4)
                ->get();
        }

        return $products->map(fn ($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'image_url'   => $p->image_url,
            'price'       => $p->price,
            'description' => $p->description,
        ]);
    }
}
