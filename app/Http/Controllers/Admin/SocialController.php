<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The manual social queue: rows the pipeline composed but could not (or was
 * told not to) transmit. Copy the text, paste it into the platform's composer,
 * hit "Mark posted" (optionally with the live post's URL). Also shows what is
 * still pending for the hourly command and recent history, so the whole
 * outbox is auditable from one screen.
 */
class SocialController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Social/Index', [
            'queue' => SocialPost::ready()
                ->with('post:id,title,slug')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($row) => $this->present($row)),
            'pending' => SocialPost::pending()
                ->with('post:id,title,slug')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($row) => $this->present($row)),
            'recent' => SocialPost::whereIn('status', [SocialPost::STATUS_POSTED, SocialPost::STATUS_SKIPPED])
                ->with('post:id,title,slug')
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->map(fn ($row) => $this->present($row)),
            'enabled' => (bool) config('services.social.enabled'),
        ]);
    }

    public function markPosted(Request $request, SocialPost $socialPost): RedirectResponse
    {
        $data = $request->validate([
            'external_url' => 'nullable|url|max:255',
        ]);

        $socialPost->update([
            'status'       => SocialPost::STATUS_POSTED,
            'posted_at'    => now(),
            'external_url' => $data['external_url'] ?? null,
            'last_error'   => null,
        ]);

        return back()->with('success', "Marked as posted on {$socialPost->platform}.");
    }

    public function skip(SocialPost $socialPost): RedirectResponse
    {
        $socialPost->update(['status' => SocialPost::STATUS_SKIPPED]);

        return back()->with('success', "Skipped the {$socialPost->platform} announcement.");
    }

    private function present(SocialPost $row): array
    {
        return [
            'id'          => $row->id,
            'platform'    => $row->platform,
            'status'      => $row->status,
            'body'        => $row->body,
            'chars'       => mb_strlen($row->body),
            'attempts'    => $row->attempts,
            'last_error'  => $row->last_error,
            'external_url' => $row->external_url,
            'posted_at'   => $row->posted_at?->toIso8601String(),
            'queued_at'   => $row->created_at->toIso8601String(),
            'post_title'  => $row->post->title ?? "Post #{$row->post_id}",
            'post_url'    => $row->post ? route('posts.show', $row->post->slug) : null,
            'compose_url' => $this->composeUrl($row),
        ];
    }

    /**
     * Deep link into the platform's composer. Bluesky supports text prefill via
     * its intent URL; Facebook forbids prefilled sharer text, so that link just
     * opens facebook.com (or the configured Page) with the copy on the clipboard.
     */
    private function composeUrl(SocialPost $row): string
    {
        if ($row->platform === 'bluesky') {
            return 'https://bsky.app/intent/compose?text=' . rawurlencode($row->body);
        }

        if ($row->platform === 'facebook') {
            $pageId = config('services.social.platforms.facebook.page_id');

            return $pageId ? "https://www.facebook.com/{$pageId}" : 'https://www.facebook.com/';
        }

        return 'https://bsky.app/';
    }
}
