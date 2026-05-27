<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateClick;
use App\Models\Post;
use App\Models\Product;
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
        ]);
    }
}
