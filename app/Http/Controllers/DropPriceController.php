<?php

namespace App\Http\Controllers;

use App\Models\DropPricePuzzle;
use App\Models\DropPriceResult;
use App\Support\DropPrice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class DropPriceController extends Controller
{
    /**
     * Aggregate play stats only become public once there's enough volume to
     * mean something — results only exist for players who saved a streak with
     * their email, so tiny counts would read as fake social proof.
     */
    public const MIN_PLAYS_FOR_STATS = 25;

    public function index(): View
    {
        // Exclude the currently-ACTIVE puzzle by id, not by date < today — this
        // correctly handles the gap before dropprice:lock has run today, where
        // yesterday's row IS today's active puzzle (mirrors the homepage).
        $activeId = DropPrice::today()?->id;

        $puzzles = DropPricePuzzle::query()
            ->select(['id', 'puzzle_number', 'date', 'product_name', 'product_image_url'])
            ->playable()
            ->where('id', '!=', $activeId ?? 0)
            ->orderByDesc('date')
            ->paginate(24)
            ->through(fn (DropPricePuzzle $p) => [
                'puzzle_number'     => $p->puzzle_number,
                'date'              => $p->date->format('M j, Y'),
                'product_name'      => $p->product_name,
                'product_image_url' => $p->product_image_url,
            ]);

        $stats = Cache::remember('dropprice.stats', now()->addHour(), function () {
            $totalPlays = DropPriceResult::count();

            return [
                'totalPuzzles' => DropPricePuzzle::query()->playable()->count(),
                'totalPlays'   => $totalPlays,
                'winRate'      => $totalPlays > 0 ? (int) round(DropPriceResult::avg('won') * 100) : null,
            ];
        });

        view()->share('serverMeta', [
            'title'       => 'Drop Price Archive | GadgetDrop',
            'description' => 'Replay every past Drop Price puzzle. Guess what it sold for — no spoilers, no time pressure.',
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('drop-price.index'),
        ]);

        return view('public.drop-price.index', [
            'puzzles'   => $puzzles,
            'stats'     => $stats,
            'showStats' => $stats['totalPlays'] >= self::MIN_PLAYS_FOR_STATS,
        ]);
    }

    public function show(DropPricePuzzle $puzzle): View|RedirectResponse
    {
        // Route binds by puzzle_number and 404s automatically on no match. This
        // guard additionally blocks anomalous rows the lock command should never
        // produce — numbered-but-unlocked, or locked-but-future-dated — using
        // the same playability rule as every other public query (defense in
        // depth; the row's price is still a secret answer either way).
        abort_unless($puzzle->isPlayable(), 404);

        $active = DropPrice::today();

        if ($active && $puzzle->id === $active->id) {
            return redirect()->route('home');
        }

        view()->share('serverMeta', [
            'title'       => "Drop Price #{$puzzle->puzzle_number} | GadgetDrop",
            'description' => "Replay Drop Price #{$puzzle->puzzle_number} — guess the price of {$puzzle->product_name}.",
            'og_image'    => null,
            'og_type'     => 'website',
            'canonical'   => route('drop-price.show', $puzzle->puzzle_number),
            // Thin/near-duplicate content across days — same noindex treatment
            // ToolController gives individual tool pages.
            'noindex'     => true,
        ]);

        return view('public.drop-price.show', ['puzzle' => $puzzle]);
    }
}
