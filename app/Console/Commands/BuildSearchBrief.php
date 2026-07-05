<?php

namespace App\Console\Commands;

use App\Models\SearchOpportunity;
use App\Models\SearchQueryDay;
use Illuminate\Console\Command;

/**
 * Render the demand-side brief the overnight content agent reads before it
 * picks topics. Deterministic markdown, capped, safe to commit.
 *
 *   php artisan search:brief            # print to stdout (/morning captures over SSH)
 *   php artisan search:brief --write    # also write daily-drop/seo-brief.md
 *
 * The brief RE-RANKS what to write; it never changes how much (cadence is the
 * law) and never overrules the editorial value gate. When there's no data it
 * says so and tells the agent to fall back to editorial judgment.
 */
class BuildSearchBrief extends Command
{
    protected $signature = 'search:brief {--write : Also write daily-drop/seo-brief.md}';

    protected $description = 'Build the SEO demand brief (daily-drop/seo-brief.md) from mined opportunities.';

    public function handle(): int
    {
        $md = $this->render();

        if ($this->option('write')) {
            $path = base_path('daily-drop/seo-brief.md');
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, $md);
            $this->info("Wrote {$path}");
        } else {
            // Raw markdown to stdout (no artisan decoration) so /morning can
            // capture it verbatim over SSH.
            $this->line($md);
        }

        return self::SUCCESS;
    }

    private function render(): string
    {
        $dataThrough = SearchQueryDay::max('date');
        $today       = now()->toDateString();

        $reviews = $this->byKind('content_gap', 5);
        $refresh = SearchOpportunity::whereIn('status', ['open', 'planned'])
            ->whereIn('kind', ['decay', 'striking_distance'])
            ->orderByDesc('score')->with('post')->first();
        $news  = $this->byKind('rising', 2);
        $avoid = $this->byKind('cannibalization', 5);

        $l = [];
        $l[] = "# SEO Brief — generated {$today}";
        $l[] = 'Data through: ' . ($dataThrough ?: 'no synced data yet') . ' (Google finalized data lags ~2 days).';
        $l[] = '';
        $l[] = '## How to use this';
        $l[] = '- These are demand signals, not orders. Pick a candidate ONLY if it passes the /drop-research value gate (real price-history, testing, or comparison value to add).';
        $l[] = '- If this brief is missing or > 3 days old, fall back to editorial judgment and say so in research.md.';
        $l[] = '- Cadence is fixed at 11 posts/week. This re-ranks WHICH topics get written, never HOW MANY.';
        $l[] = '';

        if ($reviews->isEmpty() && $news->isEmpty() && ! $refresh) {
            $l[] = '## No opportunities yet';
            $l[] = 'Not enough search data has accumulated to surface demand-backed candidates. Use editorial judgment for today and note it in research.md.';
            $l[] = '';

            return implode("\n", $l) . "\n";
        }

        $l[] = '## Review candidates (new posts — max 3)';
        foreach ($reviews->take(3) as $i => $o) {
            $e = $o->evidence ?? [];
            $l[] = ($i + 1) . ". **{$o->query}** — " . ($e['impressions'] ?? 0) . ' impr'
                . ($o->page_url ? ", best result now {$o->page_url}" : '')
                . (! empty($e['position']) ? ' at position ' . round((float) $e['position'], 1) : '');
            $l[] = '   - Value we can add: [ ] price history  [ ] testing/spec analysis  [ ] comparison';
            $l[] = '   - ' . $this->phrasings($o);
        }
        $l[] = '';

        $l[] = '## Refresh candidate (1)';
        if ($refresh) {
            $title = $refresh->post?->title ?? $refresh->query ?? 'a post';
            $url   = $refresh->post ? route('posts.show', $refresh->post->slug) : ($refresh->page_url ?? '');
            $why   = $refresh->kind === 'decay' ? 'clicks decayed vs its prior window' : 'ranking 4-15, one push from page 1';
            $l[]   = "- **{$title}** " . ($url ? "({$url}) " : '') . "— {$why}.";
            $l[]   = '   - ' . $this->phrasings($refresh);
        } else {
            $l[] = '- None flagged.';
        }
        $l[] = '';

        $l[] = '## Tip / news angles (max 4)';
        foreach ($news as $o) {
            $e = $o->evidence['rising'] ?? [];
            $l[] = "- **{$o->query}** — rising " . ($e['recent'] ?? 0) . ' vs ' . ($e['prior'] ?? 0) . ' impr week-over-week (news/tip angle).';
        }
        foreach ($reviews->slice(3, 2) as $o) {
            $l[] = "- **{$o->query}** — " . ($o->evidence['impressions'] ?? 0) . ' impr, no dedicated post (tip candidate).';
        }
        $l[] = '';

        $l[] = '## Avoid (cannibalization — do NOT create near-duplicates)';
        if ($avoid->isEmpty()) {
            $l[] = '- Nothing flagged.';
        } else {
            foreach ($avoid as $o) {
                $n = count($o->evidence['competitors'] ?? []);
                $l[] = "- **{$o->query}** — already split across {$n} of our posts; consolidate, don't add another.";
            }
        }
        $l[] = '';

        return implode("\n", $l) . "\n";
    }

    private function byKind(string $kind, int $limit)
    {
        return SearchOpportunity::open()
            ->where('kind', $kind)
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    private function phrasings(SearchOpportunity $o): string
    {
        $phrasings = collect($o->evidence['phrasings'] ?? [])
            ->pluck('query')
            ->filter()
            ->take(6)
            ->implode('", "');

        return $phrasings !== '' ? 'Phrasings: "' . $phrasings . '"' : 'Single phrasing.';
    }
}
