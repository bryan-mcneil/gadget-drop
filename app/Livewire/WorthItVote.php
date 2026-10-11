<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\WorthItVote as Vote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One-tap "Worth it" / "I'd skip" vote on a post. No accounts: identity is a
 * salted, non-reversible hash of session + IP. The unique (post_id, voter_hash)
 * index is the real double-vote gate; the session check is just UX.
 * See docs/plans/02-worth-it-voting.md §Phase 2.2.
 */
class WorthItVote extends Component
{
    /** Locked so the mounted post can't be swapped by a tampered payload. */
    #[Locked]
    public int $postId;

    /** null | 'worth' | 'skip' — this visitor's recorded choice (persists on refresh). */
    public ?string $voted = null;

    public function mount(int $postId): void
    {
        $this->postId = $postId;

        // Preload the visitor's existing choice so a page refresh shows the result
        // state, not a fresh ballot.
        $this->voted = Vote::query()
            ->where('post_id', $this->postId)
            ->where('voter_hash', $this->voterHash())
            ->value('choice');
    }

    public function vote(string $choice): void
    {
        if (! in_array($choice, [Vote::CHOICE_WORTH, Vote::CHOICE_SKIP], true)) {
            return;
        }

        // Already voted (this session/hash) — keep the recorded choice, no re-count.
        if ($this->voted !== null) {
            return;
        }

        // Low-stakes abuse guard: 10 votes/min/IP. On limit, silently keep the
        // current state — no error theater for a gadget vote.
        $key = 'worth-it:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return;
        }

        RateLimiter::hit($key, 60);

        $hash = $this->voterHash();

        // insertOrIgnore is the atomic double-vote gate: a race or a stale session
        // that lost $voted hits the unique index and is ignored (0 rows), and we
        // fall back to loading whatever choice already exists for this identity.
        $inserted = Vote::query()->insertOrIgnore([
            'post_id' => $this->postId,
            'choice' => $choice,
            'voter_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            $this->voted = Vote::query()
                ->where('post_id', $this->postId)
                ->where('voter_hash', $hash)
                ->value('choice');

            return;
        }

        $this->voted = $choice;

        // The /deals feed carries a worth% per card; bust it so a new vote shows.
        // Cheap + low-frequency, and the feed self-rebuilds hourly regardless.
        try {
            Cache::forget('deals.feed');
        } catch (\Throwable) {
            // Cache not bound — nothing to flush.
        }
    }

    /**
     * Salted, non-reversible visitor id: session id + IP, keyed by the app secret.
     * No raw IP is ever stored (GDPR-clean, matches the site's privacy posture).
     */
    private function voterHash(): string
    {
        return hash_hmac(
            'sha256',
            session()->getId().'|'.request()->ip(),
            (string) config('app.key'),
        );
    }

    public function render()
    {
        $post = Post::find($this->postId);

        return view('livewire.worth-it-vote', [
            'summary' => $post ? $post->worthItSummary() : Vote::summarize(0, 0),
        ]);
    }
}
