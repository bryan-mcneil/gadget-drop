# Plan 11 — Live Price Compare (Claude + web search)

**Goal:** a reader on a review page can press one button and get a live, first-party comparison of what the reviewed product costs at other major US retailers, rendered next to our own tracked Amazon price — turning "is this actually a good price?" from a claim into a check the reader can run themselves.
**Size:** M · **Branch:** main (no feature branches — 2026-07-18 convention) · **Depends on:** — (price-intel layer + `ClaudeExplainService` precedent already on main)
**Non-goals:** monitoring, alerting, or storing third-party prices over time (Amazon Associates §6(y) — this is a stateless on-demand lookup, nothing is persisted); any Amazon price sourced from search (§2(b)); affiliate links to third-party retailers; a queue/worker; scraping; touching `PriceIntel`, `/deals`, or the market layer.

Context for the implementer: this is the **second** Claude-API feature on the site. The first — `App\Services\ClaudeExplainService` + `App\Livewire\ExplainVerdict` — is the template for everything structural here (config-driven key, `isConfigured()`, DB-cache daily budget counter, per-IP `RateLimiter`, `Cache::lock` stampede guard, self-busting cache key, **null on any failure, never a thrown exception**). Read both files before starting. The differences from that feature are: the model gets the **web search server tool**, returns **structured JSON instead of streamed prose**, runs on Sonnet 5 rather than Haiku, and puts all arithmetic and all claim-making in PHP rather than the model.

## Phase Log

- [x] Phase 11.1 — `PriceComparison` support class (pure) + unit tests (built 2026-08-25; commit pending approval)
- [ ] Phase 11.2 — `PriceCompareService` + config + feature tests
- [ ] Phase 11.3 — `LivePriceCompare` Livewire component + view + wiring + tests
- [ ] Phase 11.4 — docs, `.env.example`, build, deployment notes

---

## Design decisions

Each of these was settled in the 2026-08-25 design session. Do not silently revisit them.

### Compliance (account-termination-class; treat as invariants)

- **Amazon's price NEVER comes from web search.** Associates §2(b): prices may only be displayed if Amazon serves the link or the data came via PA-API/Creators API. The Amazon row is **our own tracked price** (`products.price` + `price_checked_at`, sourced via Canopy/manual/PA-API), labeled as *our tracked price*, never "the Amazon price".
- **Enforced by whitelist, not blocklist.** The tool gets `allowed_domains` — an explicit list of first-party US retailers in `config/price-compare.php`. This makes an Amazon price *structurally impossible* rather than dependent on a blocklist staying complete, and it simultaneously excludes (a) price-tracker/aggregator sites, which are bad §6(y) optics and unreliable, and (b) marketplaces/resellers, which produce fake-low grey-market and refurb prices. `allowed_domains` and `blocked_domains` are **mutually exclusive — sending both is a 400**.
- **Second line of defence in PHP.** `PriceComparison::normalize()` re-checks every returned row's host against the same whitelist and drops anything off-list. The model naming a retailer we didn't ask for is a bug, not a result.
- **Nothing is persisted.** Third-party prices live only in a 24h cache entry. No table, no snapshot, no history — that is what keeps this a lookup and not "price tracking functionality" under §6(y).
- **Stateless and on-demand.** No emails, no notifications, no background refresh of third-party prices, ever.

### Honesty

- **Gate the claim, not the feature.** The comparison always renders. The *"X is currently the lowest"* line renders **only** when `price_checked_at` is within `fresh_days` (7). Past that, the widget states plainly that competitor prices are live while ours is a last-known figure from {date}, and **declines to name a winner**. Rationale: the dangerous error is not "we wrongly say Walmart wins" — it is "we wrongly say **Amazon** wins", because that is the error that flatters our own payout and sends a reader to a checkout that doesn't match what we told them. A price-truth site cannot carry a bias pointing at its own commission.
- **PHP makes every claim.** The model finds and reads listings. It does **not** compare, rank, total, or write the verdict sentence. `min()` and the winner copy are PHP, templated from the same rows the table renders. Nothing the reader sees as a factual claim is model prose.
- **Every price carries its provenance and date.** Competitor rows are stamped with the check timestamp; our row shows `price_checked_at` as an absolute date (§2(b) requires a date/time stamp adjacent to a price refreshed less than hourly — and the `<x-price-history>` label already sets this precedent).
- **"Nobody else sells this" is a result, not an error.** A large share of daily-drop picks are Amazon-exclusive ASINs, so an empty comparison is a *common* outcome and a genuinely useful one — it justifies the Amazon CTA better than any price could. It renders as a first-class finding ("We checked N retailers and couldn't find this anywhere else") and **is cached like any other result**, so Amazon-exclusive products don't re-burn budget on every click forever.

### Commerce

- **The Amazon product card stays the only prominent CTA.** The comparison table is plain text — retailer name and price, no anchors. `AdsensePrepTest::test_post_drops_the_duplicate_price_cta` asserts on the absence of a *"Check Current Prices"* block, not on link count, so it stays green; do not introduce any CTA-styled control in this widget.
- **Attribution via a collapsed `<details>`.** Anthropic's web search docs require citations when displaying API output to end users; because we reprocess structured JSON into our own table we fall under the softer "as appropriate" clause, but zero attribution is a stretch. A closed `<details>` labeled "Sources (N)" expands to `rel="nofollow noopener"` links. Attribution satisfied, visual hierarchy intact.
- **No `citations` blocks needed.** Source URLs arrive as ordinary `url` fields in the structured payload, which sidesteps the known 400 when document citations meet `output_config.format`.
- **Nothing in this widget reaches SEO.** It renders only after a user click, so server-rendered HTML — what crawlers see — contains no third-party prices and no outbound links. **Never** put a comparison result into `serverJsonLd` or `serverMeta`.

### Cost and infrastructure

- **Web search is $10 per 1,000 searches ($0.01 each) on top of tokens — and that fee is model-independent.** 4 searches cost $0.04 whichever model runs them, so the model choice only moves the token slice: Haiku 4.5 lands around $0.065–0.08 a click, Sonnet 5 around $0.07–0.09, Opus 5 around $0.10–0.14. **Budget ~$0.08 per uncached click** (~$13/day at the 150 ceiling, realistically $1–4 with cache hits).
- **Model: `claude-sonnet-5`, chosen for judgment rather than cost.** The only variant guard in this design is the model's own `exact_model_match` self-report (see Product identity), which is a pure judgment task — 128GB vs 256GB, MoGo 4 vs MoGo 4 Laser — and a wrong-variant price is the failure mode that damages a price-truth brand. Sonnet 5 costs ~15% more per click than Haiku 4.5 (~$2/day at the ceiling) to materially improve the one guard we have. Opus 5 is overkill: marginal SKU-matching gain, and thinking-by-default eats the latency budget.
- **Basic `web_search_20250305`, deliberately.** Dynamic filtering (`web_search_20260209`, Claude 4.6+) would cut input tokens by filtering results before they reach context, but it runs web search from *inside code execution* — an extra round trip against a 25s ceiling. **Latency is the binding constraint here, not cost.** Dynamic filtering is the first tuning knob once real latency numbers exist, not the starting shape.
- **Adaptive thinking is on by default on Sonnet 5**, which costs latency. Set `output_config.effort` to `medium` as the lever, and size `maxTokens` to ~8000 — thinking tokens count against it, so the 2000 that would suffice for bare JSON would truncate.
- **Three guards, all on the database cache store** (no Redis on Hostinger): global `daily_limit` 150 uncached calls/day (~$11 ceiling, realistically $1–3 with cache hits), per-IP `RateLimiter` 5/hour, and a `Cache::lock` stampede guard that re-checks the cache inside the lock.
- **24h cache, keyed with a fingerprint of our own price** so an `/admin/prices` edit self-busts it rather than serving a comparison against a price we have since changed (the `db-cache-outlives-the-data` lesson).
- **Synchronous — there is no worker.** `QUEUE_CONNECTION=database` but nothing runs `queue:work`: not `bin/deploy.sh`, not `routes/console.php`, and the hPanel cron fires `schedule:run` **hourly**. A dispatched job would sit in the `jobs` table forever. This is a button → API → JSON → Blade round trip inside one request, by design.
- **We must fail before LiteSpeed does.** The Anthropic PHP client's `RequestOptions` defaults to `timeout: 600` (10 minutes) and `maxRetries: 2` — left alone it loses every race against PHP `max_execution_time` and the LiteSpeed proxy, and the reader gets a dead 504 instead of our graceful fallback. Construct with `RequestOptions::with(timeout: 25.0, maxRetries: 0)`. **`maxRetries: 0` is load-bearing**: retries are applied to timeouts, so the default 2 would make a 25s timeout a 75s wall clock.
- **`pause_turn` means give up, not loop.** A long search turn can return `stop_reason: 'pause_turn'`; continuing it would need another round trip we have no time budget for. Treat as failure, return null.
- **Failures are never cached; empty results are.** A timeout or API error must leave the cache untouched so an immediate retry is free to succeed. A genuine "found nothing" is a real answer and is cached for the full 24h.
- **Kill switch independent of the API key.** `PRICE_COMPARE_ENABLED` (default **false**, same posture as `ADSENSE_ENABLED`) so this can be turned off in prod without pulling `ANTHROPIC_API_KEY`, which `ClaudeExplainService` also depends on.

### Product identity

- **The model self-reports its match.** No new `products` column. Each returned row carries `exact_model_match`, and the payload carries an overall `confidence`; PHP drops every non-exact row and suppresses the whole widget below the confidence floor. **This is the reason the model is Sonnet 5 rather than Haiku 4.5** — the self-report is the only variant guard in the design, so it is worth ~15% per click to make it a better one. **Residual accepted risk:** `products.name` is hand-typed in admin, so a loose name ("Anker 737 Power Bank") can still surface the wrong capacity or a Laser variant. If wrong-variant rows show up in practice, the escalation is a nullable `products.search_name` override column — deliberately deferred, not forgotten.
- **One widget per product**, rendered inside the existing `@foreach` in the end zone, so roundups get a button per product. Zero cost until clicked.

---

## Phase 11.1 — `PriceComparison` support class (pure) + unit tests

**Scope:** the pure normalize/rank/gate logic, with no HTTP and no Laravel boot. Nothing user-visible yet.

Follows the `App\Support\ArticleBody` / `PriceIntel` precedent: Support classes hold the math, Services hold the external I/O. `Unit/ArticleBodyTest` is a pure PHPUnit test with no Laravel boot — keep this one the same, which means **no hard facade dependencies in the class**.

Steps:

1. `app/Support/PriceComparison.php` — one public entry point:

   ```php
   PriceComparison::normalize(
       array $raw,            // decoded model payload
       ?float $ourPrice,
       ?CarbonInterface $ourCheckedAt,
       array $allowedHosts,
       int $freshDays = 7,
       float $confidenceFloor = 0.6,
   ): array
   ```

   Returns a view model:

   ```php
   [
       'rows'              => [ ['retailer' => 'Walmart', 'price' => 189.00, 'url' => '…', 'in_stock' => true], … ],
       'retailers_checked' => 14,
       'our_price'         => 179.99,
       'our_checked_at'    => CarbonInterface|null,
       'is_fresh'          => bool,   // ourCheckedAt within freshDays
       'winner'            => 'amazon'|'retailer'|null,   // null whenever !is_fresh
       'winner_label'      => string|null,                // PHP-templated, never model prose
       'is_empty'          => bool,   // zero surviving rows
       'suppressed'        => bool,   // confidence below floor — render nothing
   ]
   ```

2. Filtering rules, applied in order — each one drops the row silently:
   - `exact_model_match !== true`
   - `price` is null, non-numeric, `<= 0`, or absurd (`> 100x` our price, guarding a mis-parsed "$1,899" on a $19 item)
   - `url` host, after `parse_url` + stripping a leading `www.`, is not in `$allowedHosts` (registrable-suffix match, so `www.walmart.com` and `walmart.com` both pass)
   - duplicate retailer — keep the lowest price for that host
3. Sort surviving rows by price ascending. Cap at 6 rows.
4. `winner` is computed **only** when `is_fresh`: `min(rows.price)` vs `ourPrice`. Ties go to Amazon (it's the row we can actually link). When `!is_fresh`, `winner` and `winner_label` are `null` and the view renders the last-known-figure copy instead.
5. `suppressed` is true when payload `confidence < $confidenceFloor` — the caller renders the failure state, not an empty table.

Tests (`tests/Unit/PriceComparisonTest.php`, pure PHPUnit, no `RefreshDatabase`, no app boot):

- Non-exact-match rows are dropped; exact ones survive.
- A row whose host is outside the whitelist is dropped **even when the model returned it** (the defence-in-depth case).
- `www.`-prefixed and bare hosts both match the whitelist.
- Null / zero / negative / absurd-magnitude prices are dropped.
- Duplicate retailers collapse to the single lowest price.
- Rows sort ascending and cap at 6.
- Fresh + a cheaper retailer → `winner === 'retailer'`, label names that retailer and the difference.
- Fresh + Amazon lowest → `winner === 'amazon'`.
- Fresh + exact tie → `winner === 'amazon'`.
- **Stale (`ourCheckedAt` older than `freshDays`) → `winner` and `winner_label` are null even though a cheaper row exists.** (The bias guard — assert this one explicitly.)
- `ourPrice === null` → no winner, rows still render.
- Zero surviving rows → `is_empty === true` (and `is_empty` is distinguishable from `suppressed`).
- Confidence below floor → `suppressed === true`.

Commit: `feat(price-compare): add PriceComparison support class for ranking and freshness gating`

Review checklist: no facades, no Eloquent, no `now()` without an injected clock; every claim-producing branch has a test; the stale-suppresses-winner test exists.

---

## Phase 11.2 — `PriceCompareService` + config + feature tests

**Scope:** the Claude call, the guards, the budget. Still nothing user-visible.

Steps:

1. `config/price-compare.php`:

   ```php
   return [
       'enabled'          => env('PRICE_COMPARE_ENABLED', false),
       'model'            => env('PRICE_COMPARE_MODEL', 'claude-sonnet-5'),
       'daily_limit'      => (int) env('PRICE_COMPARE_DAILY_LIMIT', 150),
       'effort'           => 'medium', // latency lever — Sonnet 5 thinks by default
       'max_uses'         => 4,      // web searches per request
       'timeout'          => 25.0,   // seconds — we must fail before LiteSpeed
       'cache_hours'      => 24,
       'fresh_days'       => 7,      // winner claim gate
       'confidence_floor' => 0.6,
       'retailers'        => [
           'walmart.com', 'bestbuy.com', 'target.com', 'costco.com',
           'samsclub.com', 'newegg.com', 'bhphotovideo.com', 'adorama.com',
           'microcenter.com', 'crutchfield.com', 'staples.com',
           'homedepot.com', 'lowes.com', 'gamestop.com',
       ],
   ];
   ```

   The API key is **shared** with the explain feature: keep reading `config('services.claude.api_key')`. Keep the retailer list ~12–15 entries — an over-long domain filter returns the `request_too_large` search error.

2. `app/Services/PriceCompareService.php`, shaped exactly like `ClaudeExplainService`:

   - `isEnabled()`, `isConfigured()`, `usageToday()`, `requestsRemainingToday()`, `countRequest()` (increment **before** the call — an attempt that reaches the provider bills either way), cache key `price-compare.usage.{Y-m-d}`.
   - `compare(Product $product): ?array` returns the **raw decoded payload** (normalization is 11.1's job) or **null on any failure whatsoever** — not enabled, not configured, budget exhausted, auth, rate limit, network, timeout, `pause_turn`, malformed JSON. Never throws.
   - Client construction:

     ```php
     $client = new Client(
         apiKey: $this->apiKey,
         requestOptions: RequestOptions::with(timeout: 25.0, maxRetries: 0),
     );
     ```

   - Request:

     ```php
     $message = $client->messages->create(
         model: config('price-compare.model'),
         maxTokens: 8000,   // thinking tokens count against this
         system: self::SYSTEM_PROMPT,
         messages: [['role' => 'user', 'content' => json_encode($facts, JSON_UNESCAPED_SLASHES)]],
         tools: [[
             'type' => 'web_search_20250305',   // basic, not dynamic filtering — see Cost and infrastructure
             'name' => 'web_search',
             'max_uses' => config('price-compare.max_uses'),
             'allowed_domains' => config('price-compare.retailers'),
             'user_location' => ['type' => 'approximate', 'country' => 'US'],
         ]],
         outputConfig: [
             'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA],
             'effort' => config('price-compare.effort'),
         ],
     );
     ```

     Note the shapes: **top-level args are camelCase** (`maxTokens`, `outputConfig`) while **nested tool keys stay snake_case** (`max_uses`, `allowed_domains`, `user_location`). Do not bulk-convert either way. `format` and `effort` are sibling keys **inside** `outputConfig`, never top-level. `web_search_20250305` defaults `allowed_callers` to `["direct"]`, so leave it unset.

   - `self::SCHEMA` — `additionalProperties: false`, required `confidence` (number 0–1), `retailers_checked` (integer), `results` (array of objects: `retailer` string, `price` number|null, `url` string, `in_stock` boolean, `exact_model_match` boolean).
   - `self::SYSTEM_PROMPT` must state: report only listings for the **exact** model given, on the retailer's own site; set `exact_model_match: false` for any variant/capacity/colour/generation mismatch or bundle; never report a price you did not read on the retailer page; never mention Amazon; report the item's own price, not a bundle or subscription price; do not rank, compare, or recommend — return data only. **No em dashes** (matches the house prompt convention in `ClaudeExplainService`).
   - Guards after the call, in order: `stopReason === 'pause_turn'` → null; a `web_search_tool_result` whose `content` is a single **error object** rather than a list (server-tool errors return HTTP 200 — they do not raise) → log and continue, since a partial result is still usable; first text block → `json_decode`, and null on decode failure.
   - Catch `AnthropicException` then `\Throwable`, `Log::warning` both, return null.
   - Log `usage.server_tool_use.web_search_requests` alongside token usage so real per-click spend is observable in production rather than estimated.

3. `Product` needs nothing new — `name`, `asin`, `price`, `price_checked_at` all exist.

Tests (`tests/Feature/PriceCompareServiceTest.php`) — **no test may reach the real API.** `RequestOptions` accepts a PSR-18 `transporter`, so inject a stub client returning canned responses:

- Disabled flag → `compare()` returns null and **makes no HTTP call**.
- Missing API key → null, no call.
- Budget exhausted (`daily_limit` reached) → null, no call.
- A well-formed response decodes into the expected array.
- `stop_reason: 'pause_turn'` → null.
- Malformed / non-JSON text block → null, no exception escapes.
- Transport throwing → null, no exception escapes.
- A `web_search_tool_result` error object does not abort a response that still carries a usable text block.
- `countRequest()` increments before the call, so a failed call still counts against the daily budget.

Commit: `feat(price-compare): add PriceCompareService with web search, budget and timeout guards`

Review checklist: timeout 25s **and** `maxRetries: 0`; `allowed_domains` present and `blocked_domains` absent; no path throws; usage counted before the call; key read from `services.claude.api_key`; system prompt forbids ranking and forbids mentioning Amazon.

---

## Phase 11.3 — `LivePriceCompare` Livewire component + view + wiring

**Scope:** the button, the states, the table, and the one-line insertion into the post template.

Steps:

1. `app/Livewire/LivePriceCompare.php`, modeled on `ExplainVerdict`:

   - `#[Locked] public int $productId;` — everything else is re-derived server-side from the id, so a tampered payload cannot swap the product or inject a price (the `WorthItVote` / `ExplainVerdict` principle).
   - `public string $phase = 'idle';` — `idle | ready | empty | gated | throttled | failed`.
   - `compare()`:
     1. Return early (`phase = 'gated'`) when the feature is disabled, unconfigured, or the product has no `price`.
     2. Build the cache key: `price-compare.v1.{productId}.{ourPriceFingerprint}` — the fingerprint makes an `/admin/prices` edit self-busting. TTL `cache_hours`.
     3. Cache hit → normalize → render. **Rate-limit only the uncached path** (a cache hit costs nothing and must not be throttled) — same reasoning as `ExplainVerdict`.
     4. `RateLimiter::tooManyAttempts("price-compare:{ip}", 5)` over 3600s → `phase = 'throttled'`.
     5. `Cache::lock("price-compare-lock.{$key}", 30)->block(8, …)`, re-checking the cache inside the lock; on `LockTimeoutException`, look once more at the cache.
     6. `PriceCompareService::compare()` → null → `phase = 'failed'`, **write nothing to the cache**.
     7. `PriceComparison::normalize(...)` → `suppressed` → `failed`; `is_empty` → `empty` **and still cache it**; otherwise `ready` and cache.
   - No custom Alpine. Use `wire:loading` / `wire:target` for the pending state — registering an Alpine component would mean an `alpine:init` handler in `app.js` with a `destroy()` cleanup, and this needs none of that.

2. `resources/views/livewire/live-price-compare.blade.php`:

   - **idle:** a secondary-styled button, *not* CTA-styled — "Compare prices at other retailers". A one-line note that it runs a live search.
   - **loading:** `wire:loading` skeleton rows plus "Checking {N} retailers…". Any pulse animation needs a `motion-reduce:` guard (Plan 10 motion budget).
   - **ready:** the table — retailer name and price as **plain text, no anchors**; our row labeled "Our tracked Amazon price" with `price_checked_at` as an absolute date; then either the PHP-templated winner line or, when stale, the last-known-figure copy with no winner.
   - **empty:** "We checked {N} retailers and couldn't find this at any of them. This one looks Amazon-exclusive."
   - **all states:** a "Checked {date}" stamp, an explicit *"Prices found by an AI search of retailer sites and may be out of date — always confirm at checkout"* line, and the collapsed `<details>` "Sources (N)" with `rel="nofollow noopener"` links.
   - **failed / throttled:** a quiet retry line. Never a raw error.
   - Contrast rule: secondary metadata on this light surface is `text-gray-500`, **never** `text-gray-400` (fails AA on white).
   - Blade component gotcha: no directives *between* a component tag's attributes — use bound `:` attributes for conditionals.

3. `resources/views/public/show.blade.php` — one line inside the existing `@foreach`, immediately after `<x-price-history>` (reads as: our tracked price → what else is out there → the timing call):

   ```blade
   @livewire('live-price-compare', ['productId' => $product['id']], key('compare-'.$product['id']))
   ```

   The `key()` is required — without it, Livewire reuses one component instance across a roundup's products.

Tests (`tests/Feature/Livewire/LivePriceCompareTest.php`) — mock `PriceCompareService` at the container level, exactly as `ExplainVerdictTest` mocks `ClaudeExplainService`. Use post type `article` (never `tech_news` — the sqlite CHECK predates that enum widening).

- Feature disabled → button does not render on the post page.
- Product with no price → `gated`, service never called.
- Happy path → `ready`, table shows the retailer names and prices, service called once.
- Second call with the same key → cache hit, service **not** called again.
- Changing `products.price` busts the key → service called again (the fingerprint test).
- Service returns null → `failed`, and **nothing is written to the cache** (assert a retry calls the service again).
- Empty result → `empty` phase **and** cached (assert the service is not called twice).
- Stale `price_checked_at` → the winner line is absent while rows still render.
- Rate limit exceeded on the uncached path → `throttled`; a cache hit is **not** throttled.
- Rendered output contains no `amazon.com` link inside the comparison block and no "Check Current Prices" text (keeps `AdsensePrepTest` honest).
- Server-rendered page (no interaction) contains no third-party prices — the SEO guarantee.

Commit: `feat(price-compare): add live retailer price comparison widget to review pages`

Review checklist: `#[Locked]` on `productId`; no price or product data accepted from the client; rate limiter only on the uncached path; failures not cached, empties cached; `key()` on the Livewire tag; no anchors in the table; `motion-reduce:` on any animation; `text-gray-500` for metadata.

---

## Phase 11.4 — Docs, env, build, deployment

Steps:

1. `.env.example` — add commented `PRICE_COMPARE_ENABLED=false`, `PRICE_COMPARE_MODEL=claude-sonnet-5`, `PRICE_COMPARE_DAILY_LIMIT=150` under the existing Claude block.
2. `CLAUDE.md` — a short subsection under the price-intelligence section covering: the §2(b) whitelist rule (Amazon never from search), the freshness gate on the winner claim, the 24h price-fingerprinted cache, the synchronous-no-worker constraint, and the `timeout` / `maxRetries: 0` requirement.
3. `docs/plans/README.md` — add the plan 11 row to the table.
4. `npm run build` — the widget introduces new Blade classes and Tailwind purges on content scan. If classes look stale afterwards, `php artisan view:clear` then rebuild. Per the `build-nondeterministic-churn` memory, a rebuild rehashes the whole bundle; that churn is expected and ships with this plan.
5. Full suite green before review (baseline 560).

Commit: `docs(price-compare): document the live comparison feature and enable flag`

## Deployment

1. `npm run build` locally, commit `public/build`.
2. `bash bin/deploy.sh` on the server (`git pull` → composer → migrate → `optimize` → `images:optimize`). **No migration in this plan** — nothing is persisted, by design.
3. Set on prod `.env`: `PRICE_COMPARE_ENABLED=true` (`ANTHROPIC_API_KEY` is already present for the explain feature). Then `php artisan optimize` so the cached config picks it up — the flag is read through `config()`, and a stale `config.php` will silently keep the feature dark.
4. Purge the Hostinger CDN cache (hPanel → Performance → CDN).
5. Verify on a live review page: button renders → click → result within ~25s → "Checked {date}" stamp present → Sources disclosure links carry `rel="nofollow noopener"` → **no Amazon price anywhere in the comparison table**.
6. Verify the budget counter is moving: the daily key is `price-compare.usage.{Y-m-d}` on the database cache store.
7. Watch `storage/logs` for the first day. The two signals that matter: real `web_search_requests` per click versus the $0.07 estimate, and the timeout rate against the 25s ceiling. If timeouts are common, tune in this order before touching the timeout — the web server's limit is the one we cannot raise: drop `effort` to `low`, then `max_uses` to 3. If **token cost** rather than latency turns out to be the pressure, the move is the opposite direction: switch to `web_search_20260209` for dynamic filtering, accepting the code-execution round trip.

## Known accepted risks

- **Wrong-variant rows.** The model's `exact_model_match` self-report is the only variant guard (design-session decision — no schema change); Sonnet 5 was chosen to strengthen it, not to eliminate the risk. Escalation if it bites: a nullable `products.search_name` override column.
- **Occasional timeouts.** Accepted in exchange for no worker and no polling. The reader sees a retry line, and the failure is not cached, so a retry can succeed.
- **Coverage gaps.** A fixed global whitelist will miss category-specific retailers (AV specialists for some products, Micro Center for others). Escalation: per-category whitelists.

## Build Log

(append one line per phase)

- **11.1 (2026-08-25)** — `app/Support/PriceComparison.php` + `tests/Unit/PriceComparisonTest.php` (32 tests, pure PHPUnit, no Laravel boot). Suite green at 604. Six decisions were made during implementation that the plan did not pin down; each is deliberate:
  - **Injected clock.** `normalize()` takes an optional 7th argument `?CarbonInterface $now = null`, defaulting to `Carbon::now()`. The plan's six-argument signature is unchanged for callers; this satisfies the phase's own review checklist ("no `now()` without an injected clock") without forcing every test through `Carbon::setTestNow()`.
  - **Out-of-stock rows render but never win.** The winner is computed from in-stock rows only. Crowning a listing the reader cannot actually buy is exactly the confident-and-wrong claim the freshness gate exists to prevent, and the winner copy says "lowest in-stock price" so a cheaper out-of-stock row in the table does not read as a contradiction. When nothing found is in stock, there is no verdict at all. A row with no `in_stock` key is assumed in stock (absence is not evidence).
  - **Missing `confidence` fails closed.** The schema makes it required, so its absence means a malformed payload, and a malformed payload is not evidence of anything. `suppressed = true`.
  - **`retailers_checked` is clamped to the whitelist size** and falls back to it when absent or non-numeric. "We checked N retailers" renders as our own claim, so the model must not be able to inflate N.
  - **URL scheme validation.** Only `http`/`https` survive, capped at 2048 chars. These become `<details>` links in 11.3, so a `javascript:` URL must never reach the view.
  - **Retailer display names are sanitised.** Model-authored text, so it is whitespace-collapsed, stripped of control characters, capped at 60 chars, and falls back to the matched whitelist domain when unusable. Dedupe keys on the matched domain, never on this string.
  - Also worth knowing for 11.3: `suppressed`, `is_empty`, and "ready" are mutually exclusive, so the view can branch on them in that order. A suppressed payload returns `rows => []` rather than leaking unvetted rows.
