<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Support\TruthReport;
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
                ->add(Url::create(route('deals'))->setPriority(0.8)->setChangeFrequency('daily'))
                ->add(Url::create(route('drop-price.index'))->setPriority(0.7)->setChangeFrequency('daily'))
                ->add(Url::create(route('about'))->setPriority(0.5)->setChangeFrequency('monthly'))
                ->add(Url::create(route('how-we-review'))->setPriority(0.5)->setChangeFrequency('monthly'));

            // Published Truth Reports (config + artifact double-gate) — static
            // once published, so they only change when a report is re-generated.
            foreach (array_keys(TruthReport::published()) as $truthSlug) {
                $sitemap->add(Url::create(route('truth.show', $truthSlug))
                    ->setPriority(0.8)
                    ->setChangeFrequency('yearly'));
            }

            Post::published()
                // Posts flagged noindex in the SEO panel stay out of the sitemap
                // so the two signals agree.
                ->whereDoesntHave('seoMeta', fn ($q) => $q->where('noindex', true))
                ->latest('published_at')
                ->select(['id', 'slug', 'published_at', 'updated_at'])
                ->chunkById(500, fn ($posts) => $posts->each(fn ($post) => $sitemap->add(
                    Url::create(route('posts.show', $post->slug))
                        ->setLastModificationDate($post->updated_at)
                        ->setPriority(0.8)
                        ->setChangeFrequency('weekly')
                )));

            // Only categories with enough published posts to be a real landing page.
            // Below the threshold the page is a thin link grid — it also renders
            // noindex (PublicController::category), so the two signals agree.
            Category::whereHas('posts', fn ($q) => $q->published(), '>=', Category::SITEMAP_MIN_POSTS)
                ->get(['slug'])
                ->each(fn ($cat) => $sitemap->add(
                    Url::create(route('category', $cat->slug))
                        ->setPriority(0.6)
                        ->setChangeFrequency('weekly')
                ));

            return $sitemap->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
