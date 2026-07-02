<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(): Response
    {
        // The sitemap is identical between crawls until content changes; cache the
        // rendered XML for a day so we don't rebuild it (and load every post) on
        // each request. chunkById bounds memory while building it.
        $xml = Cache::remember('sitemap.xml', now()->addDay(), function () {
            $sitemap = Sitemap::create()
                ->add(Url::create(route('home'))->setPriority(1.0)->setChangeFrequency('daily'))
                ->add(Url::create(route('news'))->setPriority(0.9)->setChangeFrequency('daily'))
                ->add(Url::create(route('drop-price.index'))->setPriority(0.7)->setChangeFrequency('daily'));

            Post::published()
                ->latest('published_at')
                ->select(['id', 'slug', 'published_at', 'updated_at'])
                ->chunkById(500, fn ($posts) => $posts->each(fn ($post) => $sitemap->add(
                    Url::create(route('posts.show', $post->slug))
                        ->setLastModificationDate($post->updated_at)
                        ->setPriority(0.8)
                        ->setChangeFrequency('weekly')
                )));

            Category::all(['slug'])->each(fn ($cat) => $sitemap->add(
                Url::create(route('category', $cat->slug))
                    ->setPriority(0.6)
                    ->setChangeFrequency('weekly')
            ));

            return $sitemap->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
