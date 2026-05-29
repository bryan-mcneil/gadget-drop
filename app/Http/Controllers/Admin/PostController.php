<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Posts/Index', [
            'posts' => Post::with('user')
                ->latest()
                ->paginate(15)
                ->through(fn ($p) => [
                    'id'           => $p->id,
                    'title'        => $p->title,
                    'status'       => $p->status,
                    'published_at' => $p->published_at?->toDateString(),
                    'author'       => $p->user->name,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Posts/Form', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'tags'       => Tag::orderBy('name')->get(['id', 'name']),
            'products'   => Product::orderBy('name')->get(['id', 'name', 'price']),
            'authors'    => User::where('id', '!=', 1)->orderBy('name')->get(['id', 'name', 'avatar_url', 'bio']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'slug'           => 'nullable|string|max:255|unique:posts,slug',
            'excerpt'        => 'nullable|string|max:500',
            'body'           => 'required|string',
            'featured_image' => 'nullable|string',
            'image_1'        => 'nullable|string',
            'image_2'        => 'nullable|string',
            'image_3'        => 'nullable|string',
            'type'           => 'nullable|in:article,tech_tip',
            'source_url'     => 'nullable|string|max:500',
            'status'         => 'required|in:draft,published,scheduled',
            'published_at'   => 'nullable|date',
            'user_id'        => 'nullable|exists:users,id',
            'category_ids'   => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids'        => 'nullable|array',
            'tag_ids.*'      => 'exists:tags,id',
            'product_ids'    => 'nullable|array',
            'product_ids.*'  => 'exists:products,id',
            'seo.meta_title'       => 'nullable|string|max:70',
            'seo.meta_description' => 'nullable|string|max:320',
            'seo.focus_keyword'    => 'nullable|string|max:100',
        ]);

        $post = Post::create([
            ...$data,
            'user_id' => $data['user_id'] ?? $request->user()->id,
            'slug'    => $data['slug'] ?: Str::slug($data['title']),
        ]);

        $post->categories()->sync($data['category_ids'] ?? []);
        $post->tags()->sync($data['tag_ids'] ?? []);

        if (!empty($data['product_ids'])) {
            $post->products()->sync(
                collect($data['product_ids'])->mapWithKeys(fn ($id, $i) => [$id => ['display_order' => $i]])
            );
        }

        if (!empty($data['seo'])) {
            $post->seoMeta()->create(['post_id' => $post->id] + $data['seo']);
        }

        return redirect()->route('admin.posts.index')->with('success', 'Post created.');
    }

    public function edit(Post $post): Response
    {
        $post->load(['categories', 'tags', 'products', 'seoMeta']);

        return Inertia::render('Admin/Posts/Form', [
            'post'       => $post,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'tags'       => Tag::orderBy('name')->get(['id', 'name']),
            'products'   => Product::orderBy('name')->get(['id', 'name', 'price']),
            'authors'    => User::where('id', '!=', 1)->orderBy('name')->get(['id', 'name', 'avatar_url', 'bio']),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'slug'           => 'nullable|string|max:255|unique:posts,slug,' . $post->id,
            'excerpt'        => 'nullable|string|max:500',
            'body'           => 'required|string',
            'featured_image' => 'nullable|string',
            'image_1'        => 'nullable|string',
            'image_2'        => 'nullable|string',
            'image_3'        => 'nullable|string',
            'type'           => 'nullable|in:article,tech_tip',
            'source_url'     => 'nullable|string|max:500',
            'status'         => 'required|in:draft,published,scheduled',
            'published_at'   => 'nullable|date',
            'category_ids'   => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'tag_ids'        => 'nullable|array',
            'tag_ids.*'      => 'exists:tags,id',
            'product_ids'    => 'nullable|array',
            'product_ids.*'  => 'exists:products,id',
            'seo.meta_title'       => 'nullable|string|max:70',
            'seo.meta_description' => 'nullable|string|max:320',
            'seo.focus_keyword'    => 'nullable|string|max:100',
        ]);

        $post->update([
            ...$data,
            'slug' => $data['slug'] ?: Str::slug($data['title']),
        ]);

        $post->categories()->sync($data['category_ids'] ?? []);
        $post->tags()->sync($data['tag_ids'] ?? []);

        if (!empty($data['product_ids'])) {
            $post->products()->sync(
                collect($data['product_ids'])->mapWithKeys(fn ($id, $i) => [$id => ['display_order' => $i]])
            );
        }

        if (!empty($data['seo'])) {
            $post->seoMeta()->updateOrCreate(['post_id' => $post->id], $data['seo']);
        }

        return redirect()->route('admin.posts.index')->with('success', 'Post updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'Post deleted.');
    }
}
