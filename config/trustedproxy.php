<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Hostinger's CDN ("hcdn") fronts this site, so the address LiteSpeed hands
| PHP as REMOTE_ADDR may be the CDN edge rather than the reader. Every
| per-IP guard on the site is computed from request()->ip(): the login
| throttle (App\Http\Requests\Auth\LoginRequest), the contact/newsletter/
| watch-signup throttles, the Worth It vote dedupe, and the per-reader
| limits on both Claude features. If REMOTE_ADDR is the CDN, all of those
| collapse into a single site-wide bucket.
|
| WHY THIS FILE AND NOT bootstrap/app.php: TrustProxies reads the proxy list
| at request time --
|
|     $trustedIps = $this->proxies() ?: config('trustedproxy.proxies');
|
| -- whereas the withMiddleware() closure in bootstrap/app.php runs from
| afterResolving(HttpKernel::class), which fires BEFORE the config
| bootstrapper. config() is not available there, and env() is null once
| `php artisan optimize` has cached the config. So the header bitmask (a
| compile-time constant) is set in bootstrap/app.php and the proxy list
| lives here, where it is read after config is loaded and survives caching.
|
| VALUES:
|   ''            trust nothing. request()->ip() is REMOTE_ADDR verbatim,
|                 forwarded headers ignored. Safe default; also correct if
|                 the CDN already rewrites REMOTE_ADDR for us.
|   'REMOTE_ADDR' trust whoever is directly connected -- the CDN edge --
|                 without having to enumerate its addresses. This is the
|                 setting to use behind hcdn.
|   '1.2.3.4,…'   an explicit comma-separated list of proxy IPs or CIDRs.
|
| Note that Laravel's '*' does NOT mean "trust every hop in the chain": it
| resolves to setTrustedProxies([REMOTE_ADDR]), i.e. exactly the same single
| trusted entry as 'REMOTE_ADDR'. Either way, only the directly-connected
| peer is trusted, so a reader who injects their own X-Forwarded-For cannot
| impersonate another reader -- Symfony walks the chain from the right and
| stops at the first entry that is not a trusted proxy. TrustProxiesTest
| pins that behaviour.
|
*/

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [

    'proxies' => $proxies === '' ? null : $proxies,

];
