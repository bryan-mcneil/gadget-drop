<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Discovery endpoints for search / answer engines: the IndexNow key file and an
 * llms.txt site map for LLMs. Both are plain text and safe to cache.
 */
class SearchController extends Controller
{
    /** IndexNow verifies ownership by fetching this key file. 404 when unset. */
    public function indexNowKey(): Response
    {
        $key = (string) config('search.indexnow_key');

        abort_if($key === '', 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * A markdown map of the site for LLMs (brand, key pages, categories, latest
     * reviews). Cached on the default (database) store; busted by the same
     * NavigationData::flush() post-save hook that clears the nav caches.
     */
    public function llms(): Response
    {
        $body = Cache::remember('search.llms_txt', now()->addDay(), fn () => $this->build());

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function build(): string
    {
        $lines = [
            '# GadgetDrop',
            '',
            '> Independent tech & gadget reviews with real, tracked price history — 30/90-day'
                .' lows, averages and honest "buy or wait" verdicts on every product we cover.',
            '',
            '## Key pages',
            '- [Deals]('.route('deals').'): products currently below their tracked 90-day average',
            '- [Latest news]('.route('news').'): buyer-focused tech news',
            '- [How we review]('.route('how-we-review').'): our testing and rating method',
            '',
            '## Categories',
        ];

        $categories = Category::whereHas('posts', fn ($q) => $q->published())
            ->orderBy('name')
            ->get(['name', 'slug']);

        foreach ($categories as $category) {
            $lines[] = '- ['.$category->name.']('.route('category', $category->slug).')';
        }

        $lines[] = '';
        $lines[] = '## Latest reviews';

        $posts = Post::published()
            ->latest('published_at')
            ->take(20)
            ->get(['title', 'slug']);

        foreach ($posts as $post) {
            $lines[] = '- ['.$post->title.']('.route('posts.show', $post->slug).')';
        }

        return implode("\n", $lines)."\n";
    }
}
