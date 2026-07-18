---
name: mcp-server-invariants
description: Confirmed-safe laravel/mcp v0.8.2 patterns verified in Plan 04 Phase 4.1 — don't re-flag in later MCP phases
metadata:
  type: project
---

Plan 04 (Price-Truth MCP, `feature/price-truth-mcp`) stands up `laravel/mcp` v0.8.2 at `POST /mcp`. Facts verified against vendor source in the 4.1 review — treat as confirmed-safe; do not re-flag in 4.2–4.4 reviews.

**Why:** these were plausible-looking BLOCKER candidates that turned out correct after reading `vendor/laravel/mcp/src`; re-flagging wastes a review cycle.

**How to apply:**
- **CSRF exclusion is real and safe.** `Mcp::web()` (Registrar.php) registers the POST route via `Route::group([], routes/ai.php)` with NO `web` group — no session/CSRF/VerifyCsrfToken. Package adds GET/DELETE→405 handlers + `ReorderJsonAccept`/`AddWwwAuthenticateHeader` middleware. Read-only + throttled ⇒ token-less POST is intended. Don't flag "missing CSRF".
- **Two-limit throttle with distinct `by()` keys is correct, not redundant.** `ThrottleRequests::handleRequestUsingNamedLimiter` computes the counter key as `md5($limiterName.$limit->key)` (or `$limiterName.':'.$limit->key` unhashed). Two `Limit`s in one named limiter that share `->by($request->ip())` would collide into one counter. The `mcp` limiter deliberately uses `mcp-min:`/`mcp-day:` prefixes so the 30/min and 300/day counters stay independent. This distinct-key trick is REQUIRED whenever a named limiter returns multiple Limits keyed off the same value.
- **Route caching:** `McpServiceProvider::registerRoutes` early-returns when `routesAreCached() && !runningInConsole()`, relying on the cache built during `route:cache` (which runs in console). This is the standard pattern — Phase 4.4's `route:cache` rehearsal is the right place to verify Livewire+MCP both survive; not a 4.1 concern.
- **Middleware order gotcha:** in `routes/ai.php` the array is `['throttle:mcp', McpCacheControl::class]` — throttle is outer, so a 429 short-circuits before `McpCacheControl`, and throttled responses lack `Cache-Control: no-store`. Harmless (POSTs aren't CDN-cached; 429s carry no price data) but if honesty-header coverage on error responses ever matters, McpCacheControl must come first.
- **Instructions link `#deal-verdicts`:** the server instructions string points at `how-we-review#deal-verdicts`, but `resources/views/public/how-we-review.blade.php` has no `id="deal-verdicts"` anchor yet. Plan 4.3 extracts the methodology partial — ensure the anchor lands then (or the fragment stays dead).
