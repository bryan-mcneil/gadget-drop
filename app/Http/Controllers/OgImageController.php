<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\OgImageGenerator;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OgImageController extends Controller
{
    /**
     * Serve the branded social-share card for a post (generated on a cache miss).
     */
    public function post(Post $post, OgImageGenerator $generator): BinaryFileResponse
    {
        abort_unless($post->status === 'published', 404);

        return response()
            ->file($generator->forPost($post), [
                'Content-Type'  => 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            ]);
    }

    /**
     * Serve the generic site card — the fallback for pages without their own image.
     */
    public function default(OgImageGenerator $generator): BinaryFileResponse
    {
        return response()
            ->file($generator->forDefault(), [
                'Content-Type'  => 'image/jpeg',
                'Cache-Control' => 'public, max-age=604800',
            ]);
    }

    /**
     * Dev preview gallery — shows every post's card inside mock Twitter/Facebook
     * frames so the share cards can be eyeballed without posting anything.
     */
    public function preview(): View
    {
        $posts = Post::published()
            ->whereNotNull('featured_image')
            ->latest('published_at')
            ->get(['id', 'type', 'title', 'slug', 'excerpt'])
            ->map(fn (Post $p) => [
                'title'       => $p->title,
                'type'        => $p->type,
                'excerpt'     => $p->excerpt,
                'url'         => route('posts.show', $p->slug),
                'card'        => route('og.posts.show', $p->slug),
                'domain'      => parse_url(config('app.url'), PHP_URL_HOST) ?: 'gadgetdrop.tech',
            ]);

        return view('og-preview', ['posts' => $posts]);
    }
}
