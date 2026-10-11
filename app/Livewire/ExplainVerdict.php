<?php

namespace App\Livewire;

use App\Models\ReleaseCycle;
use App\Services\ClaudeExplainService;
use App\Support\BuyOrWait;
use App\Support\PriceIntel;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Explain this verdict" — an on-demand, Claude-written explanation of a
 * buy-or-wait verdict, streamed into the page and visibly labeled as
 * Claude's own words. Never recomputes the verdict ({@see BuyOrWait}) or
 * accepts any of its data from the client: everything is re-derived
 * server-side from the cycle slug alone, same principle as
 * {@see \App\Livewire\WorthItVote}'s Locked postId.
 *
 * @see docs — Claude-role plan (2026-08-24 session)
 */
class ExplainVerdict extends Component
{
    /** Locked so the cycle being explained can't be swapped by a tampered payload. */
    #[Locked]
    public string $cycleSlug;

    /** idle | gated | explained | failed */
    public string $phase = 'idle';

    public function mount(string $cycleSlug): void
    {
        $this->cycleSlug = $cycleSlug;
    }

    public function explain(): void
    {
        $data = $this->resolveVerdictData();

        if (! $data) {
            return;
        }

        ['cycle' => $cycle, 'stats' => $stats, 'verdict' => $verdict] = $data;

        // Honesty gate: no tracked price history to weigh in means nothing
        // for Claude to explain beyond what PriceIntel already says on the
        // page. Never send an incomplete payload and hope it fills the gap.
        if (! ($stats['has_stats'] ?? false)) {
            $this->phase = 'gated';

            return;
        }

        $key = $this->cacheKey($cycle, $verdict, $stats);

        $cached = Cache::get($key);

        if ($cached !== null) {
            $this->stream(content: $cached, replace: true, name: 'explanation');
            $this->phase = 'explained';

            return;
        }

        // Only the expensive (uncached) path is rate-limited — a cache hit
        // costs nothing and shouldn't be throttled.
        $rateLimitKey = 'explain-verdict:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->phase = 'failed';

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $facts = $this->buildFacts($cycle, $stats, $verdict);

        try {
            $text = Cache::lock("explain-lock.{$key}", 30)->block(8, function () use ($key, $facts) {
                // Re-check inside the lock: another request may have just
                // finished generating this exact explanation while we waited.
                $cached = Cache::get($key);

                if ($cached !== null) {
                    $this->stream(content: $cached, replace: true, name: 'explanation');

                    return $cached;
                }

                return app(ClaudeExplainService::class)->streamExplanation(
                    $facts,
                    fn (string $chunk) => $this->stream(content: $chunk, replace: false, name: 'explanation'),
                );
            });
        } catch (LockTimeoutException) {
            // Another request is generating this one right now. One more
            // look at the cache: it likely just finished.
            $text = Cache::get($key);

            if ($text !== null) {
                $this->stream(content: $text, replace: true, name: 'explanation');
            }
        }

        if ($text === null) {
            $this->phase = 'failed';

            return;
        }

        Cache::put($key, $text, now()->addDays(60));
        $this->phase = 'explained';
    }

    /**
     * @return array{cycle: ReleaseCycle, stats: ?array<string, mixed>, verdict: array<string, mixed>}|null
     */
    private function resolveVerdictData(): ?array
    {
        $cycle = ReleaseCycle::where('slug', $this->cycleSlug)->first();

        if (! $cycle) {
            return null;
        }

        $product = $cycle->flagshipProduct();
        $stats = $product ? PriceIntel::stats($product->id) : null;
        $verdict = BuyOrWait::verdict($cycle, $stats);

        return ['cycle' => $cycle, 'stats' => $stats, 'verdict' => $verdict];
    }

    /**
     * Self-busting key, same idiom as {@see BuyOrWait::verdict()}: a changed
     * input is simply a different key, so a stale explanation can never be
     * served and nothing needs an explicit flush() on verdict change.
     *
     * @param  array<string, mixed>  $verdict
     * @param  array<string, mixed>  $stats
     */
    private function cacheKey(ReleaseCycle $cycle, array $verdict, array $stats): string
    {
        return sprintf(
            'claude-explain.v1.%s.%s.%s.%s.%s.%s',
            $cycle->slug,
            $verdict['verdict'],
            $verdict['confidence'],
            $stats['current'] ?? 'none',
            $stats['avg90'] ?? 'none',
            $cycle->verified_at?->toDateString() ?? 'none',
        );
    }

    /**
     * The exact facts Claude is allowed to reason from. Raw dates are
     * included for grounding; cycle_position/months_since_release/
     * data_is_stale are the SAME derived numbers BuyOrWait::verdict() and
     * the get_buy_or_wait_verdict MCP tool already compute and expose, so
     * Claude never has to do its own date arithmetic to explain staleness
     * or cycle position (which risks inventing a fact not actually given).
     *
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $verdict
     * @return array<string, mixed>
     */
    private function buildFacts(ReleaseCycle $cycle, array $stats, array $verdict): array
    {
        return [
            'line_name' => $cycle->name,
            'current_model_name' => $cycle->last_release_name,
            'price' => [
                'current' => $stats['current'],
                'checked_at' => $stats['checked_at'],
                'avg_90_day' => $stats['avg90'],
            ],
            'cycle' => [
                'on_sale_date' => $cycle->last_release_at?->toDateString(),
                'typical_cadence_months' => $cycle->cadence_months,
                'months_since_release' => round($cycle->monthsSinceRelease(), 1),
                'cycle_position' => $verdict['cycle_position'],
                'last_checked_date' => $cycle->verified_at?->toDateString(),
                'data_is_stale' => $cycle->isStale(),
                'stale_after_months' => ReleaseCycle::STALE_AFTER_MONTHS,
            ],
            'verdict_label' => BuyOrWait::label($verdict['verdict']),
            'confidence' => $verdict['confidence'],
            'as_of_date' => now()->toDateString(),
        ];
    }

    public function render()
    {
        return view('livewire.explain-verdict');
    }
}
