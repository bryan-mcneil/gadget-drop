<?php

namespace App\Providers;

use App\Support\DealsFeed;
use App\Support\NavigationData;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // migrate:fresh/refresh/reset/rollback and db:wipe are banned on every
        // environment except testing (sqlite :memory:) unless .env opts in with
        // DB_ALLOW_DESTRUCTIVE=true. Last line of defense for the real MySQL
        // data if tests ever boot against it again (see tests/bootstrap.php).
        DB::prohibitDestructiveCommands(
            ! $this->app->environment('testing') && ! config('database.allow_destructive'),
        );

        Vite::prefetch(concurrency: 3);

        // Force https for all generated URLs (sitemap, canonical, OG) in production
        // so a proxy/CDN reporting http can't leak http:// links and split SEO signals.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('tools', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // The Price-Truth MCP server (routes/ai.php → POST /mcp). Two per-IP
        // ceilings — a burst cap and a daily cap — counted on the default
        // (database) cache store on Hostinger: structural protection for
        // shared-hosting CPU against over-enthusiastic agents. The two limits
        // use distinct keys because ThrottleRequests hashes the named limiter +
        // limit key, so a shared IP key would collide the two counters. If
        // strain shows, drop to 15/min (documented in /for-ai, Phase 4.3).
        RateLimiter::for('mcp', function (Request $request) {
            return [
                Limit::perMinute(30)->by('mcp-min:'.$request->ip()),
                Limit::perDay(300)->by('mcp-day:'.$request->ip()),
            ];
        });

        // Public Blade layout gets the same navigation data the admin (Inertia) does,
        // plus the live tracked-deals count for the header's Deals pill (one cached
        // read of the already-memoised DealsFeed; 0 hides the pill).
        View::composer('layouts.public', function ($view) {
            $view->with('navigation', NavigationData::get());
            $view->with('dealsLiveCount', count(DealsFeed::get()));
        });
    }
}
