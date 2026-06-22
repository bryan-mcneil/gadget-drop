<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClick;
use App\Models\DropPricePuzzle;
use App\Models\DropPriceResult;
use App\Models\Post;
use App\Models\Product;
use App\Support\DropPrice;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'posts'         => Post::count(),
                'published'     => Post::where('status', 'published')->count(),
                'products'      => Product::count(),
                'clicks_today'  => AffiliateClick::whereDate('clicked_at', today())->count(),
            ],
            'recentPosts' => Post::with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($p) => [
                    'id'           => $p->id,
                    'title'        => $p->title,
                    'status'       => $p->status,
                    'published_at' => $p->published_at?->toDateString(),
                    'author'       => $p->user?->name ?? '—',
                ]),
            // Read-only Drop Price summary. This is the admin side of the secrecy
            // boundary — showing the answer price here is fine (admin-only), so we
            // surface it openly for at-a-glance verification of the live puzzle.
            'dropPrice' => $this->dropPriceSummary(),
        ]);
    }

    /**
     * Today's live puzzle (with engagement counts) plus the next queued preset,
     * if an admin pre-set one via `dropprice:lock --date=…`. Read-only — there is
     * no override action here in v1; the CLI is the override mechanism.
     *
     * @return array{today: ?array, upcoming: ?array}
     */
    private function dropPriceSummary(): array
    {
        $today = DropPrice::today();

        $todaySummary = null;
        if ($today) {
            $todaySummary = [
                'number'    => $today->puzzle_number,
                'name'      => $today->product_name,
                'image'     => $today->product_image_url,
                'price'     => $today->price,
                'date'      => $today->date?->toDateString(),
                'is_preset' => (bool) $today->is_preset,
                'product_id' => $today->product_id,
                'plays'     => DropPriceResult::where('drop_price_puzzle_id', $today->id)->count(),
                'wins'      => DropPriceResult::where('drop_price_puzzle_id', $today->id)->where('won', true)->count(),
            ];
        }

        // A future-dated row only exists when an admin queued it; surface it so the
        // CLI override is verifiable from the dashboard. It may still be unlocked
        // (puzzle_number null) until the cron locks it on its date.
        $next = DropPricePuzzle::query()
            ->whereDate('date', '>', now()->toDateString())
            ->orderBy('date')
            ->first();

        $upcoming = $next ? [
            'number'    => $next->puzzle_number,
            'name'      => $next->product_name,
            'price'     => $next->price,
            'date'      => $next->date?->toDateString(),
            'is_preset' => (bool) $next->is_preset,
        ] : null;

        return ['today' => $todaySummary, 'upcoming' => $upcoming];
    }
}
