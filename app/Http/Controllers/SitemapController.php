<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home'))->setPriority(1.0)->setChangeFrequency('daily'));

        Post::published()
            ->latest('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(fn ($post) => $sitemap->add(
                Url::create(route('posts.show', $post->slug))
                    ->setLastModificationDate($post->updated_at)
                    ->setPriority(0.8)
                    ->setChangeFrequency('weekly')
            ));

        Category::all(['slug'])->each(fn ($cat) => $sitemap->add(
            Url::create(route('category', $cat->slug))
                ->setPriority(0.6)
                ->setChangeFrequency('weekly')
        ));

        return response($sitemap->render(), 200, ['Content-Type' => 'application/xml']);
    }
}
