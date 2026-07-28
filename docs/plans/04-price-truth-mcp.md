# Plan 04 — Price-Truth MCP Server

**Goal:** expose GadgetDrop's price intelligence to AI agents as an MCP server at `https://gadgetdrop.tech/mcp` — tools for price history, deal verdicts, and the active deals feed. First-mover position: independent, consumer-side "is this actually a good deal?" for the agent era. Every tool response carries the review URL + the `/out/{product}` affiliate URL, so attribution survives agent-mediated shopping.
**Size:** M · **Branch:** `feature/price-truth-mcp` · **Depends on:** Plan 01 (methodology page to cite)
**Non-goals:** write operations of any kind; OAuth (public read-only + throttle is the v1 posture); per-agent accounts; exposing anything not already public on the site.

## Phase Log

- [x] Phase 4.1 — Package install + server skeleton + throttle (commit: fdbf4ba + c48f3bd)
- [x] Phase 4.2 — Core tools: history, verdict, deals (commit: `d30941c` — one combined 4.2–4.4 commit + one combined review, run 2026-07-22; findings recorded in gd-code-reviewer memory `mcp-server-invariants`, both write-ups it asked for are now in the Build Log)
- [x] Phase 4.3 — Search tool + methodology resource + `/for-ai` docs page (commit: `d30941c`)
- [x] Phase 4.4 — Hardening, usage counter, prod verification (commit: `d30941c`) — **plan complete; prod checklist + MCP directory submission still run post-deploy (Bryan)**

## Design decisions

- **Package: official `laravel/mcp`** (Laravel team, Streamable HTTP, tools/resources/prompts, middleware support). Phase 4.1 verifies Laravel 13 compat at install time; if incompatible, fall back to evaluating `php-mcp/laravel` and STOP for review before proceeding.
- **Read-only, cache-backed, shared-hosting-respectful.** Every tool reads `PriceIntel::stats()` (db-cached 6h) or the `deals.feed` cache (1h). A tool call should almost never trigger fresh computation beyond what a page view would.
- **Honesty gates propagate.** Tools return `null` stats with an explicit `"reason": "insufficient history (need ≥2 snapshots spanning ≥14 days)"` — the gates are a feature, agents should quote them.
- **Affiliate transparency:** every response includes a `disclosure` field ("GadgetDrop earns commission on Amazon purchases via the included link") + `affiliate_url` + `review_url`. Agents relaying the link inherit the disclosure. This is Associates-safe framing — never hide the relationship.
- **Identify by ASIN or slug/name search** — agents usually hold an ASIN or a product name, not our IDs.

---

## Phase 4.1 — Package install + server skeleton + throttle

**Scope:** `laravel/mcp` installed; a `GadgetDropServer` answering `initialize`/`tools/list` with one trivial tool; rate limiting.

Steps:
1. `composer require laravel/mcp --prefer-source`. Read the package's published docs/readme AS INSTALLED (`vendor/laravel/mcp`) — follow ITS current registration idiom (expected shape: `routes/ai.php` with `Mcp::web('/mcp', GadgetDropServer::class)`; adapt to what the installed version actually provides and note the reality in the Build Log).
2. `app/Mcp/GadgetDropServer.php`: name "GadgetDrop Price Truth", version `1.0.0`, instructions string = one paragraph: what the data is (independent price snapshots for products we review), what it is NOT (live Amazon prices), the honesty gates, the disclosure sentence, link to methodology.
3. First tool `PingTool` (returns server time + product count) to prove the pipe.
4. Rate limiting: `RateLimiter::for('mcp', ...)` — 30/min per IP + 300/day per IP (db cache backed) in `AppServiceProvider`; apply via the server/route middleware. Also `Cache-Control: no-store` on responses (CDN must not cache POST bodies anyway, but be explicit — hcdn fronting).
5. CSRF: `/mcp` must be excluded from web CSRF if registered in the web group — follow the package's route registration (it handles this; verify, don't assume).

Tests (`tests/Feature/Mcp/McpServerTest.php`): JSON-RPC `initialize` handshake 200 + server name; `tools/list` contains `ping`; `tools/call` ping returns count; throttle returns 429 past limit (RateLimiter::clear in setUp); CSRF exclusion works (POST without token succeeds).

Commit: `feat(mcp): laravel/mcp server skeleton at /mcp with throttling`

Review checklist: package pinned in composer.json (`^` on a 0.x = risk — pin minor if pre-1.0); no auth bypass wider than `/mcp`; instructions string has the disclosure; nothing writes.

## Phase 4.2 — Core tools: history, verdict, deals

**Scope:** the three tools that ARE the product.

Steps — one tool class each under `app/Mcp/Tools/`, thin wrappers over existing support classes; shared private resolver `ProductResolver` (ASIN exact → slug → name LIKE, published-review products only):
1. `GetPriceHistory` — input: `{asin?: string, query?: string}` (exactly one required; validate). Output: product identity, `current`, `checked_at`, `tracking_since`, 30/90-day low/avg/high, sparkline points (date+price pairs), verdict + drop_pct, `has_stats` + gate reason when false, `review_url`, `affiliate_url`, `disclosure`, `methodology_url`.
2. `GetDealVerdict` — same resolver; output: one-sentence human verdict ("$149 is 18% below its 90-day average — a good deal per our tracking, last checked 2h ago") + the structured verdict block. The sentence is assembled server-side so agents quote OUR framing (honest, dated, hedged).
3. `ListTrackedDeals` — no input (or `{limit?: int ≤ 24}`); reads the `deals.feed` cache (same array /deals renders); returns entries incl. verdict, drop_pct, low30, worth_pct when present, URLs + disclosure once at top level.
4. Every tool: try/catch → MCP error result with a calm message (never a stack trace); responses kept lean (no full post bodies).

Tests (`tests/Feature/Mcp/McpToolsTest.php`, seeded like `DealsPageTest` + `PriceHistoryWidgetTest`): resolver precedence (asin beats query; unknown → helpful not-tracked error listing the search tool); gated product returns nulls + reason; verdict sentence contains price, pct, recency; deals tool respects honesty gates + limit; affiliate URL points at `route('affiliate.redirect')` (NEVER a raw amazon.com URL — assert it).

Commit: `feat(mcp): price history, deal verdict, tracked deals tools`

Review checklist: no raw Amazon URLs anywhere in responses (grep the diff); all reads cached; input validation rejects both-params/neither-params; error paths tested; `checked_at` always present (the minimum-truth rule).

## Phase 4.3 — Search tool + methodology resource + `/for-ai` docs page

**Scope:** discoverability for agents and humans configuring them.

Steps:
1. `SearchTrackedProducts` tool — `{query: string}` → up to 10 matches (name, brand, asin, has_stats, review_url). The "what can I even ask about?" affordance.
2. MCP **resource**: `methodology` — the deal-verdict methodology text (source it from the same content as `/how-we-review#deal-verdicts`; don't fork the copy — extract a shared partial/string).
3. `/for-ai` Blade page (public, indexable, serverMeta): what the server offers, the endpoint URL, config snippets (Claude Code: `claude mcp add --transport http gadgetdrop https://gadgetdrop.tech/mcp`; Claude Desktop/ChatGPT equivalents), rate limits, disclosure, link to methodology. Add `/for-ai` + `/mcp` mention to `SearchController::llms()` output (the llms.txt already exists — extend it).
4. Manual (Bryan, guided checklist in this plan): submit to MCP directories (mcpservers.org and peers) once prod-verified in 4.4.

Tests: search tool casing/partials/limit; resource returns methodology text; `/for-ai` renders (PublicPagesTest pattern) + llms.txt contains the new lines (SearchPingTest/llms test pattern exists — extend).

Commit: `feat(mcp): product search tool, methodology resource, /for-ai docs`

Review checklist: llms.txt stays curated (no sitemap dump); `/for-ai` has serverMeta + is in the sitemap; config snippets tested by actually running the Claude Code add command locally.

## Phase 4.4 — Hardening, usage counter, prod verification

**Scope:** ship-shape.

Steps:
1. Usage counter: increment a daily db-cache key per tool (`mcp.calls.{tool}.{Y-m-d}`, 35-day TTL) inside a lightweight middleware/wrapper — `/gd-health` reads the last 7 days. No PII, no new table.
2. Payload cap: reject inputs > 1KB; cap deals limit at 24.
3. `php artisan route:cache` locally + full suite + manual Livewire form check (the known caution) — MCP routes must survive route caching.
4. MCP Inspector (`npx @modelcontextprotocol/inspector`) against local: run the full tool matrix once; screenshot/log into the Build Log.
5. Prod verify checklist (post-deploy): initialize handshake from an external network; one call per tool; throttle fires; LiteSpeed passes POST bodies (no .htaccess surprises); hcdn does not cache `/mcp` responses (two calls, differing `server time` via ping).

Tests: usage counter increments (feature); payload cap 4xx; route:cache smoke covered by CI suite run after `route:cache` in a workflow step? — NO: don't add route:cache to CI (config-cache footgun family); keep it a local pre-deploy checklist item documented here.

Commit: `feat(mcp): usage metering, payload caps, prod-readiness hardening`

## Testing summary

Feature: ~18 new tests under `tests/Feature/Mcp/`. E2E: MCP Inspector manual matrix (logged, not automated) — Dusk doesn't apply to JSON-RPC. Suite target: +~18 green.

## Deployment

Standard template + deltas: `composer install --no-dev` on server picks up `laravel/mcp` (it's in `require`, not require-dev — verified: `require` block, `^0.8.2`); pre-deploy local `route:cache` rehearsal (step 3 above); post-deploy run the 4.4 prod checklist; THEN submit directories (4.3 step 4). No migrations. Additional 4.3 deltas: `npm run build` must be part of the deploy commit (`/for-ai` introduces classes like `whitespace-pre` that the committed bundle doesn't have yet — verified present after a local build, then reverted to keep the review diff source-only), and post-deploy bust the day-long `sitemap.xml` + `search.llms_txt` cache keys (`php artisan cache:forget` or tinker) so `/for-ai` + the MCP lines appear without waiting a day; purge the hcdn CDN as usual.

## Maintenance

- **Young package:** review `laravel/mcp` changelog before any `composer update`; MCP spec revisions can rename wire concepts — pin and upgrade deliberately, re-run the Inspector matrix after every bump.
- `/gd-health` reads the usage counters (calls/day per tool) — growth here is the leading indicator the AI-era bet is paying off; report it monthly.
- When Plan 06 ships, add `GetBuyOrWaitVerdict` as a fifth tool (one phase, same patterns).
- Revisit auth posture only if abuse appears (Sanctum tokens are the package-supported path).

## Risks

- **Shared-hosting CPU under agent enthusiasm:** throttles are the guard; if hcdn/LiteSpeed strain shows, drop to 15/min without shame (documented in /for-ai).
- **Package API drift pre-1.0:** mitigated by pinning + the "read vendor docs as installed" instruction in 4.1.
- **Agents misquoting stale prices as live:** every response embeds `checked_at` + the "not live Amazon prices" framing in server instructions — the strongest lever we have; accept residual risk.

## Build Log

(append one line per phase)

- 4.1 (2026-07, commits fdbf4ba + c48f3bd): `laravel/mcp ^0.8.2` (0.x caret = minor-pinned); registration reality: `Mcp::web('/mcp', GadgetDropServer::class)` in `routes/ai.php`, auto-loaded OUTSIDE the web group (no session/CSRF — verified by test); throttle `mcp` = 30/min + 300/day per IP with distinct limiter keys; `McpCacheControl` forces no-store; 7 tests in `McpServerTest`.
- 4.2 (2026-07-22): `DealsController::buildFeed()` extracted to `App\Support\DealsFeed` (constants aliased on the controller for existing view/test refs) so `/deals` and `list_tracked_deals` share one builder + the 1h `deals.feed` cache. Resolver at `App\Mcp\Support\ProductResolver` (ASIN exact → review-slug exact → name LIKE, published non-tip/news reviews only, LIKE wildcards escaped); shared response shape in `App\Mcp\Concerns\BuildsPriceTruthResponses` (disclosure, /out/ affiliate URLs, gate reason from the real PriceIntel constants, checked_at always present). Validation is manual in-tool (calm `Response::error`, exactly-one identifier). Conscious divergence from step 4's per-tool try/catch: EXPECTED errors return calm `Response::error` in-tool; UNEXPECTED throwables rely on the package's global handler (`vendor/laravel/mcp/src/Server.php:213`), which returns a calm generic `-32603` when `app.debug` is false — prod-safe (APP_DEBUG=false there), and debug envs get the real trace, which is what you want locally. Tests in `McpToolsTest` incl. a no-raw-amazon.com sweep across all four tools.
- 4.3 (2026-07-22): `search_tracked_products` (name/brand LIKE + ASIN exact, cap 10); the `#deal-verdicts` section of /how-we-review extracted VERBATIM into `public/partials/deal-methodology.blade.php` — the page includes it, the `methodology` resource (`gadgetdrop://methodology/deal-verdicts`, text/markdown) renders the same partial and strips it to text, so the copy cannot fork; `/for-ai` page (indexable, serverMeta, sitemap) + llms.txt "For AI agents" section. The `claude mcp add --transport http gadgetdrop <url>` snippet verified live against local (`✔ Connected`, then removed). 12 tests in `McpDiscoveryTest`. Directory submission (step 4) = Bryan, after prod verify.
- 4.4 (2026-07-22): `McpPayloadCap` (413 + JSON-RPC error body on >1KB tool arguments) + `McpUsageMeter` (`mcp.calls.{tool}.{Y-m-d}`, 35-day TTL, sanitized bounded key, counts only requests that pass the cap; package catches JsonRpcException at Server.php:211 and all other Throwables at :213 so post-`$next` metering always runs); middleware order throttle → cache-control → cap → meter, reasons documented in routes/ai.php. `route:cache` rehearsal: cached, `/mcp` + `/for-ai` + Livewire routes verified present, MCP+Livewire suites green under cached routes, then cleared. MCP Inspector CLI matrix run against local Herd (all 5 tools + resources/list + resources/read + both-params and not-tracked error paths, real data) — log at scratchpad `mcp-inspector-matrix.log`. 5 tests in `McpHardeningTest`. Full suite 453 green. Prod checklist (external handshake, per-tool calls, throttle fire, LiteSpeed POST, hcdn no-cache) runs post-deploy.
