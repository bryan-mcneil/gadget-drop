<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class ToolController extends Controller
{
    public function index(): View
    {
        $tools = collect(config('tools'))->map(fn ($tool, $slug) => [
            'slug'        => $slug,
            'name'        => $tool['name'],
            'description' => $tool['description'],
            'icon'        => $tool['icon'],
            'category'    => $tool['category'] ?? null,
        ])->values();

        view()->share('serverMeta', [
            'title'       => 'Free Online Tools | GadgetDrop',
            'description' => 'Free browser-based tools for developers and everyday users. Validate JSON, minify JS & CSS, convert and crop images. No sign-up, no server upload.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('tools.index'),
        ]);

        return view('public.tools.index', ['tools' => $tools]);
    }

    public function jsonValidator(): View      { return $this->tool('json-validator', 'json-validator'); }
    public function jsCssMinifier(): View       { return $this->tool('js-css-minifier', 'js-css-minifier'); }
    public function imageEditor(): View         { return $this->tool('image-editor', 'image-editor', 'crop'); }
    public function imageConverter(): View      { return $this->tool('image-converter', 'image-converter'); }
    public function imageCropper(): View        { return $this->tool('image-cropper', 'image-cropper'); }
    public function backgroundRemover(): View   { return $this->tool('background-remover', 'background-remover'); }
    public function passwordGenerator(): View   { return $this->tool('password-generator', 'password-generator'); }
    public function base64Encoder(): View       { return $this->tool('base64-encoder', 'base64-encoder'); }
    public function colorPalette(): View        { return $this->tool('color-palette', 'color-palette'); }
    public function metaTagPreviewer(): View    { return $this->tool('meta-tag-previewer', 'meta-tag-previewer'); }

    private function tool(string $configKey, string $view, ?string $initialTool = null): View
    {
        $tool = config("tools.{$configKey}");

        view()->share('serverMeta', [
            'title'       => $tool['meta_title'],
            'description' => $tool['meta_description'],
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('tools.' . $configKey),
        ]);

        return view("public.tools.{$view}", [
            'sidebarProducts' => $this->getSidebarProducts($tool['related_tags']),
            'metaTitle'       => $tool['meta_title'],
            'metaDescription' => $tool['meta_description'],
            'toolName'        => $tool['name'],
            'initialTool'     => $initialTool,
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
