<?php

use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\CacheViteAssets;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CacheViteAssets::class,
        ]);

        $middleware->alias([
            'api.key' => ApiKeyMiddleware::class,
        ]);

        // X-Forwarded-For ONLY, deliberately narrower than Laravel's default
        // bitmask. url()/route() build every canonical and og:url from the
        // request host, so trusting X-Forwarded-Host would let a caller
        // dictate the URL we hand Google. Proto/port are left untrusted too:
        // nothing trusts them today and the site already generates https
        // correctly, so widening here would change behaviour for no reason.
        // The proxy LIST is config/trustedproxy.php -- it cannot be set here,
        // see the note in that file.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 404s render the Blade error view (resources/views/errors/404.blade.php),
        // which extends the public layout. Laravel resolves it automatically.
    })->create();
