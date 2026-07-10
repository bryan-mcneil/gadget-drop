<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\SearchIndexCheck;
use App\Models\SearchOpportunity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * A glanceable Search Intel summary for the /morning checklist (surfaced over
 * SSH): open-opportunity count, the top few by score, and recent posts not yet
 * confirmed indexed by Google.
 *
 *   php artisan search:status            # full
 *   php artisan search:status --compact  # one-line-ish for the checklist
 */
class SearchStatus extends Command
{
    protected $signature = 'search:status {--compact : Terse single-block summary}';

    protected $description = 'Summarise open search opportunities and index coverage for the morning checklist.';

    public function handle(): int
    {
        $openCount = SearchOpportunity::open()->count();
        $unindexed = $this->unindexedRecentPosts();

        if ($this->option('compact')) {
            $this->line("SEO: {$openCount} open opportunit".($openCount === 1 ? 'y' : 'ies')
                .' · '.$unindexed->count().' recent post(s) not confirmed indexed');

            return self::SUCCESS;
        }

        $this->info("Open opportunities: {$openCount}");
        foreach (SearchOpportunity::open()->orderByDesc('score')->limit(3)->get() as $o) {
            $this->line(sprintf('  %-18s score %-7s %s', $o->kind, (string) $o->score, $o->query ?? ('post #'.$o->post_id)));
        }

        $this->info('Recent posts not confirmed indexed: '.$unindexed->count());
        foreach ($unindexed->take(10) as $post) {
            $days = $post->published_at ? (int) $post->published_at->diffInDays(now()) : 0;
            $this->line("  {$post->slug} (published {$days}d ago)");
        }

        return self::SUCCESS;
    }

    /**
     * Posts published in the last 30 days with no PASS index check (never
     * checked, or last verdict not indexed).
     *
     * @return Collection<int, Post>
     */
    private function unindexedRecentPosts()
    {
        $recent = Post::published()->where('published_at', '>=', now()->subDays(30))->get();

        $indexed = SearchIndexCheck::whereIn('post_id', $recent->pluck('id'))
            ->where('verdict', 'PASS')
            ->distinct()
            ->pluck('post_id')
            ->flip();

        return $recent->reject(fn (Post $p) => isset($indexed[$p->id]))->values();
    }
}
