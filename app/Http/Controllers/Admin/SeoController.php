<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SearchIndexCheck;
use App\Models\SearchOpportunity;
use App\Models\SearchSiteDay;
use App\Models\SearchSubmission;
use App\Services\GoogleSearchConsoleService;
use App\Services\IndexNowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Search Intel dashboard — the whole loop on one page: the clicks/impressions
 * trend, the ranked opportunity list (with dismiss / plan / done actions), index
 * coverage of recent posts (with a manual re-ping), week-over-week movers, and
 * the outbound submission log. Read-mostly; the few actions round-trip to the
 * search_opportunities table or fire a manual ping.
 */
class SeoController extends Controller
{
    private const KINDS = ['striking_distance', 'ctr_fix', 'content_gap', 'decay', 'cannibalization', 'rising', 'bing_gap'];

    public function index(Request $request): Response
    {
        $kind = $request->query('kind');
        $status = $request->query('status', 'open');

        return Inertia::render('Admin/Seo/Index', [
            'filters' => ['kind' => $kind, 'status' => $status, 'kinds' => self::KINDS],
            'trend' => $this->trend(),
            'opportunities' => $this->opportunities($kind, $status),
            'coverage' => $this->coverage(),
            'movers' => $this->movers(),
            'submissions' => SearchSubmission::latest('submitted_at')->limit(20)->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'url' => $s->url,
                    'engine' => $s->engine,
                    'trigger' => $s->trigger,
                    'response_code' => $s->response_code,
                    'submitted_at' => $s->submitted_at?->toIso8601String(),
                ]),
        ]);
    }

    public function updateOpportunity(Request $request, SearchOpportunity $opportunity): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:open,planned,done,dismissed']);

        $opportunity->update(['status' => $data['status']]);

        return back()->with('success', "Opportunity #{$opportunity->id} marked {$data['status']}.");
    }

    public function reping(Post $post): RedirectResponse
    {
        $url = route('posts.show', $post->slug);
        app(IndexNowService::class)->submit([$url, route('home')], 'manual');

        if (($status = app(GoogleSearchConsoleService::class)->submitSitemap()) !== null) {
            SearchSubmission::create([
                'url' => (string) config('search.sitemap_url'),
                'engine' => 'google_sitemap',
                'trigger' => 'manual',
                'response_code' => $status,
                'submitted_at' => now(),
            ]);
        }

        return back()->with('success', "Re-pinged {$post->slug}.");
    }

    /**
     * 90-day clicks trend as pre-computed SVG polyline points (no chart dep),
     * one line per engine/type, on a shared y-scale, plus 28-day totals.
     */
    private function trend(): array
    {
        $width = 720;
        $height = 160;
        $dates = collect(range(89, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $rows = SearchSiteDay::where('date', '>=', $dates->first())->get();

        $sources = [
            ['label' => 'Google Web', 'color' => '#4f46e5', 'source' => 'google', 'type' => 'web'],
            ['label' => 'Discover',   'color' => '#0ea5e9', 'source' => 'google', 'type' => 'discover'],
            ['label' => 'Bing',       'color' => '#059669', 'source' => 'bing',   'type' => null],
        ];

        $series = [];
        foreach ($sources as $s) {
            $byDate = $rows->where('source', $s['source'])
                ->when($s['type'], fn ($c) => $c->where('search_type', $s['type']))
                ->groupBy(fn ($r) => $r->date->toDateString());

            $series[$s['label']] = $dates->map(fn ($d) => (int) ($byDate->get($d)?->sum('clicks') ?? 0))->all();
        }

        $max = collect($series)->flatten()->max() ?: 1;

        $lines = [];
        foreach ($sources as $s) {
            $clicks = $series[$s['label']];
            $lines[] = [
                'label' => $s['label'],
                'color' => $s['color'],
                'points' => $this->points($clicks, $max, $width, $height),
                'clicks_28' => array_sum(array_slice($clicks, -28)),
            ];
        }

        return ['lines' => $lines, 'width' => $width, 'height' => $height, 'max' => $max];
    }

    /** @param array<int, int> $values */
    private function points(array $values, float $max, int $w, int $h): string
    {
        $n = count($values);
        if ($n === 0) {
            return '';
        }

        $coords = [];
        foreach ($values as $i => $v) {
            $x = $n > 1 ? round($i / ($n - 1) * $w, 1) : $w / 2;
            $y = $max > 0 ? round($h - ($v / $max) * ($h - 8) - 4, 1) : $h - 4;
            $coords[] = "{$x},{$y}";
        }

        return implode(' ', $coords);
    }

    private function opportunities(?string $kind, string $status)
    {
        return SearchOpportunity::query()
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('post:id,slug,title')
            ->orderByDesc('score')
            ->limit(100)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'kind' => $o->kind,
                'query' => $o->query,
                'score' => (float) $o->score,
                'status' => $o->status,
                'post' => $o->post ? ['slug' => $o->post->slug, 'title' => $o->post->title] : null,
                'evidence' => $o->evidence,
                'last_seen' => $o->last_seen_at?->toDateString(),
            ]);
    }

    /** Recent published posts not yet confirmed indexed by Google. */
    private function coverage()
    {
        $recent = Post::published()->where('published_at', '>=', now()->subDays(30))
            ->orderByDesc('published_at')->get(['id', 'slug', 'title', 'published_at']);

        $checks = SearchIndexCheck::whereIn('post_id', $recent->pluck('id'))
            ->orderByDesc('checked_at')->orderByDesc('id')
            ->get()->groupBy('post_id');

        return $recent->map(function ($p) use ($checks) {
            $check = $checks->get($p->id)?->first();

            return [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'days' => $p->published_at ? (int) $p->published_at->diffInDays(now()) : 0,
                'verdict' => $check?->verdict,
                'indexed' => $check?->verdict === 'PASS',
                'checked_at' => $check?->checked_at?->toDateString(),
            ];
        })->reject(fn ($p) => $p['indexed'])->values();
    }

    /** Best/worst week-over-week click movers per post. */
    private function movers(): array
    {
        $recent = DB::table('search_page_days')->whereNotNull('post_id')
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->groupBy('post_id')->pluck(DB::raw('SUM(clicks)'), 'post_id');

        $prior = DB::table('search_page_days')->whereNotNull('post_id')
            ->where('date', '>=', now()->subDays(14)->toDateString())
            ->where('date', '<', now()->subDays(7)->toDateString())
            ->groupBy('post_id')->pluck(DB::raw('SUM(clicks)'), 'post_id');

        $ids = collect($recent->keys())->merge($prior->keys())->unique();

        if ($ids->isEmpty()) {
            return ['gainers' => [], 'losers' => []];
        }

        $titles = Post::whereIn('id', $ids)->pluck('title', 'id');

        $deltas = $ids->map(fn ($id) => [
            'id' => (int) $id,
            'title' => $titles[$id] ?? "post #{$id}",
            'delta' => (int) ($recent[$id] ?? 0) - (int) ($prior[$id] ?? 0),
        ])->filter(fn ($m) => $m['delta'] !== 0)->sortByDesc('delta')->values();

        return [
            'gainers' => $deltas->take(5)->all(),
            'losers' => $deltas->reverse()->take(5)->filter(fn ($m) => $m['delta'] < 0)->values()->all(),
        ];
    }
}
