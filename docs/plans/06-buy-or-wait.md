# Plan 06 — Buy-or-Wait Engine

**Goal:** own the empty niche: living verdict pages ("Should I buy AirPods Pro now or wait?") combining editorial release-cycle data with our own price curves. Kakaku.com proves buy-timing is a durable consumer behavior; US search intent is huge and no dedicated tool exists. Formalizes the "## Buy or Wait?" sections the news pipeline already writes, and (final phase) becomes the killer MCP tool.
**Size:** L — the biggest plan; phases 6.1–6.2 are safe foundations, 6.3+ each gets a go/no-go review with Bryan.
**Branch:** `feature/buy-or-wait` · **Depends on:** Plan 01; Plan 04 (for Phase 6.5)
**Non-goals:** rumor aggregation (we cite announced patterns + history, not leaks); per-product release predictions for the long tail (categories first); automated scraping of release news (editorial curation with source URLs, matching the pipeline's SOURCE_URL discipline).

## Phase Log

- [x] Phase 6.1: `release_cycles` schema + seeded editorial dataset (commit: `eee9a02`)
- [x] Phase 6.2: `BuyOrWait` verdict engine (pure, unit-tested) (commit: `eee9a02`)
- [x] Phase 6.3: Public pages: /buy-or-wait + per-category (commit: `eee9a02`) · **go/no-go folded into the end-of-plan review at Bryan's request**
- [x] Phase 6.4: Review-page strip + news cross-links (commit: `eee9a02`)
- [x] Phase 6.5: MCP tool + llms.txt entry (commit: `eee9a02`)
- [x] Phase 6.6: Dusk pass + launch content pass (commit: `eee9a02`)
- [ ] **Fix-forward pass (opened 2026-07-27 by the retro review — see Build Log). Plan is NOT done: the feature is deployed but dark on prod, and two BLOCKERs are unfixed.**

## Design decisions

- **Category-level cycles, product-level price curves.** `release_cycles` rows describe a product line (e.g. "iPhone — September, ~12-month cadence, last: iPhone 17, Sept 2025"), each with a mandatory `source_url` + `verified_at` (the honesty discipline: no sourceless claims, and staleness is visible).
- **Verdict = cycle position × price position.** Four outcomes: `buy` (fresh cycle + good/lowest price), `wait_for_refresh` (late cycle — successor imminent), `wait_for_price` (fresh cycle but elevated price), `either` (mid-cycle, typical price — honest "no strong signal"). Confidence tiers when data is thin. Every verdict renders as a sourced, dated, hedged human sentence — never a bare command.
- **Hedge language is a product feature:** "historically", "typically announced", "based on N past cycles" — baked into the sentence builder, tested for.
- **Start with 10 lines:** iPhone, Galaxy S, Pixel, AirPods Pro, iPad, MacBook Air, Sony WH headphones, Nintendo Switch, GoPro, Kindle. Expansion criteria in Maintenance.

---

## Phase 6.1 — Schema + seeded editorial dataset

**Scope:** migration + model + seeder with sourced data.

Steps:
1. Migration `create_release_cycles_table`: `id`; `name` (product line); `slug` unique; `category_id` nullable FK (link to site categories where they map); `typical_month` tinyint nullable (1–12); `cadence_months` tinyint (12, 24…); `last_release_name` string; `last_release_at` date; `next_expected_note` string nullable (editorial: "October event expected"); `source_url` string; `verified_at` date; timestamps. Portable schema, no enums.
2. Model `ReleaseCycle`: casts, `monthsSinceRelease()`, `cyclePosition(): float` (0.0 fresh → 1.0+ overdue), scope `stale()` (`verified_at` > 6 months old — feeds `/gd-health`).
3. Seeder `ReleaseCycleSeeder` with the 10 lines — **every row researched at implementation time with a real source URL** (release dates from manufacturer newsrooms/press releases; this step includes actual research, budget an hour). Seeder is idempotent (updateOrCreate by slug) so re-running refreshes.
4. No admin CRUD yet — the seeder IS the editing interface v1 (data changes are code-reviewed like everything else; a quarterly re-verify updates `verified_at`).

Tests (`tests/Feature/ReleaseCycleTest.php`): cycle-position math across fresh/mid/overdue (frozen time); stale scope; seeder idempotence.

Commit: `feat(buywait): release_cycles schema + 10 sourced product lines`

Review checklist: every seed row has source_url + verified_at; slugs stable (they become URLs); dates verified against sources during review (spot-check 3).

## Phase 6.2 — `BuyOrWait` verdict engine

**Scope:** `app/Support/BuyOrWait.php` — pure, `ArticleBody`-style (guarded cache use, no hard facade deps), fully unit-tested.

Steps:
1. `BuyOrWait::verdict(ReleaseCycle $cycle, ?array $priceStats): array` → `{verdict, confidence, cycle_position, sentence, factors[]}`. Decision matrix (document as a table in the class docblock):
   - cycle ≥ 0.8 → `wait_for_refresh` regardless of price (a successor resets prices anyway) — unless price is `lowest` (then `either` + "clearance math" factor).
   - cycle < 0.35 + price `good|lowest` → `buy` (high confidence).
   - cycle < 0.35 + price `elevated` → `wait_for_price`.
   - else → `either`, confidence scaled by data richness (`has_stats`, cycle verified recency).
2. Sentence builder: dated, sourced, hedged — "The iPhone line has refreshed every September for 8 years (last: iPhone 17, Sept 2025). We're 10 months in — if you can wait until fall, wait." Factors array lists each input with its source (cycle source_url, PriceIntel checked_at).
3. Cache verdict per cycle slug on the db store, 6h, `PriceIntel`-style defensive try/catch.

Tests (`tests/Unit/BuyOrWaitTest.php` — pure where possible, mirror `ArticleBodyTest`'s no-boot discipline; DB-needing cases go Feature): every matrix cell; null priceStats path (cycle-only verdict, lowered confidence); sentence contains hedge words + the source recency; overdue-cycle (position > 1.0) phrasing.

Commit: `feat(buywait): verdict engine with sourced, hedged sentences`

Review checklist: matrix in docblock matches code (reviewer re-derives 3 cells); no facade hard-deps in pure paths; confidence never "high" with stale (`verified_at` > 6mo) cycle data.

## Phase 6.3 — Public pages · **go/no-go gate**

Before building: show Bryan a one-page mock (markdown sketch is fine) of the category page — this is the plan's biggest SEO surface and voice matters.

**Scope:** `/buy-or-wait` index + `/buy-or-wait/{cycle:slug}` pages.

Steps:
1. Controller + routes + Blade (SSR, serverMeta per page: "Should you buy {name} now? ({month} {year})" — the dated title is the SEO play, it self-refreshes); FAQPage JSON-LD (the verdict as Q/A); sitemap inclusion; nav placement decision with Bryan (header vs footer vs /deals cross-link only).
2. Page anatomy: verdict hero (sentence + confidence + "as of {date}"); the factors list (each sourced); price context block reusing `<x-price-history>` when a flagship tracked product maps to the line; related reviews (via category link); methodology link; Join the Drop hook ("we'll tell you when this flips" — manual for now, honest about it).
3. Index page: all lines with verdict chips, grouped buy-now vs wait.

Tests (`tests/Feature/BuyOrWaitPagesTest.php`): pages render with serverMeta + JSON-LD; unknown slug 404; sitemap; verdict sentence present; zero affiliate links outside the standard product-card component when present.

Commit: `feat(buywait): public buy-or-wait pages with dated verdicts`

## Phase 6.4 — Review-page strip + news cross-links

**Scope:** a one-line strip under the product card on reviews whose product's category maps to a cycle ("📅 Buy-or-wait: {short verdict} → full analysis"), and the `/drop-news` + `/drop-write` skill templates gain an instruction to link the relevant `/buy-or-wait/` page in their "## Buy or Wait?" sections (edit `.claude/commands/drop-news.md` + `drop-write.md` — one sentence each).

Tests: strip renders only when a mapping exists; absent otherwise (feature test both ways).

Commit: `feat(buywait): review-page strip + pipeline cross-linking`

Review checklist: strip is informational (internal link only — single CTA holds); skill-file edits reviewed by Bryan (they steer the cloud agent).

## Phase 6.5 — MCP tool + llms.txt

**Scope:** `GetBuyOrWaitVerdict` tool in the Plan 04 server (input: `{query: string}` resolving against cycle names/slugs; output: the verdict block + factors + page URL + disclosure) + llms.txt gains the /buy-or-wait index.

Tests: mirror Plan 04's tool tests (resolution, verdict shape, unknown-line error naming available lines).

Commit: `feat(buywait): buy-or-wait MCP tool`

## Phase 6.6 — Dusk pass + launch content

Steps: Dusk — index renders chips, category page shows verdict hero + factors; launch content — one editorial post introducing the tool through the pipeline; social outbox entry.

Commit: `test(buywait): Dusk coverage + launch post`

## Testing summary

Unit: engine matrix (~10). Feature: pages, strip, MCP, seeds (~15). E2E: 2 Dusk. Suite target: +~27 green.

## Deployment

Standard template + `migrate --force` + `php artisan db:seed --class=ReleaseCycleSeeder --force` (idempotent) + CDN purge. Phases deploy independently — 6.1/6.2 can ship dark (no routes) ahead of 6.3.

Deltas found during implementation:

- **`npm run build` must go in the release commit.** New Tailwind classes (`ring-{emerald,amber,sky}-200`, `hover:border-indigo-200`, `scroll-mt-24`, the chip palettes) were verified to compile, but the built bundle was reverted so the review diff stays source-only (whole-bundle rehash; see the build-churn note in CLAUDE.md).
- **Bust the sitemap cache after seeding.** `sitemap.xml` is cached for a day and the seeder doesn't touch a Post, so nothing flushes it: run `php artisan tinker --execute="Cache::forget('sitemap.xml'); Cache::forget('search.llms_txt');"` (or `App\Support\NavigationData::flush()`) after the seed, or the new URLs wait up to 24h.
- **Verdict copy is cached 6h keyed on data only.** Editing the sentence builder alone will NOT change live pages until the TTL lapses; bump `BuyOrWait::CACHE_VERSION` in the same commit (it namespaces the per-cycle verdicts *and* the `/buy-or-wait` index rows).
- Smoke URLs to add to step 6: `/buy-or-wait`, `/buy-or-wait/iphone`, `/how-we-review#buy-or-wait`, `/llms.txt`, and one review on a tracked line (strip renders).

## Maintenance

- **Quarterly re-verify** every cycle row against sources; bump `verified_at`; update `last_release_*` after launches (this is the feature's heartbeat — `/gd-health` flags stale rows and it goes red at 9 months).
- After each major launch (new iPhone etc.): update the row same-week; the verdict flips automatically.
- Expansion criteria: add a line when (a) we have ≥1 published review in the category AND (b) the line has ≥3 documented historical releases. Log additions here.
- Watch Search Console for "should i buy/wait" queries landing on these pages — feed winners back into `/drop-research` topic selection.

## Risks

- **Stale cycle data = wrong advice** — the highest-stakes honesty risk on the site. Mitigations: `verified_at` displayed on-page ("cycle data verified {date}"), confidence downgrade when stale, `/gd-health` red flag, quarterly calendar.
- **Scope creep toward rumor-blogging** — the non-goals line is the fence; announced patterns + history only.
- **SEO cannibalization** with review pages — dated verdict pages target different intent ("should I buy" vs "is it good"); monitor GSC overlap via the Search Intel loop.

## Build Log

(append one line per phase)

- **6.1 (2026-07-22)**: `release_cycles` table + `ReleaseCycle` model + `ReleaseCycleSeeder` with 10 lines, each researched live and carrying a real source URL. 9 of 10 cite a manufacturer newsroom/press release; the base-iPad row cites MacRumors because no apple.com permalink for that announcement could be located (flagged in the seeder, replace at next re-verify). `last_release_at` = the on-sale date where the source states one, else the announcement date (noted per row). Two model decisions worth reviewing: `$dateFormat` is pinned so date casts don't need a DB connection (that's what makes 6.2's pure unit tests possible), and `forProduct()` refuses to guess: name match first, category fallback only when exactly one line maps to that hub (three lines share `computers`). 13 tests.
- **6.2 (2026-07-22)**: `App\Support\BuyOrWait`: matrix, confidence tiers, sourced/dated/hedged sentence builder, factor list. Guarded cache use only, so `tests/Unit/BuyOrWaitTest.php` runs with no Laravel boot (mirrors `ArticleBodyTest`). Cache key is self-busting (cycle `updated_at` + calendar day + price inputs) plus a `CACHE_VERSION` for copy changes. 20 tests.
- **6.3 (2026-07-22)**: `/buy-or-wait` index + `/buy-or-wait/{cycle:slug}`, FAQPage JSON-LD, sitemap inclusion, `#buy-or-wait` methodology section on How We Review. Zero affiliate links on these pages (asserted). Empty index 404s rather than shipping a thin page, and the sitemap only lists it once cycles exist, so the two signals agree. **Nav placement decision made conservatively and needs Bryan's sign-off:** footer "Explore" + a /deals cross-link + the review strip, NOT the desktop header (it already crams at the `md` breakpoint). 17 tests.
- **6.4 (2026-07-22)**: `<x-buy-or-wait-strip>` under the product card on reviews whose product resolves to a line; internal link only, no CTA, so the single-affiliate-CTA rule holds. `PublicController::show()` now computes `PriceIntel::stats()` once and reuses it for both the price widget and the strip. `/drop-write` + `/drop-news` gained one sentence each instructing a single site-relative link to the relevant verdict page (**these steer the cloud agent; review the wording**). 9 tests.
- **6.5 (2026-07-22)**: `get_buy_or_wait_verdict` MCP tool (resolve by slug/name/partial, verdict + cycle block + factors + shared price payload + page URL + disclosure; unknown line names what we do cover). Server instructions gained a timing paragraph that explicitly forbids presenting a cadence as a promised release date. `/for-ai` + `llms.txt` updated. 10 tests.
- **6.6 (2026-07-22)**: 3 Dusk tests (index chips, cycle hero + sourced factors, strip → cycle-page navigation); full Dusk suite 12 green. Launch post drafted at `daily-drop/launch/news-buy-or-wait.md` (775 words, validates clean through `bin/daily-drop-build.php`, exit 0). It sits in a subdirectory ON PURPOSE so the top-level `news-*.md` glob can't sweep it into an unrelated daily drop; copy it up to `daily-drop/news-1.md` when publishing. No social-outbox code needed: `PostObserver` enqueues on publish.
- **Two live-data fixes caught by rendering real pages** (both after the seeded data went into the local DB): the generated sentence carried an em dash (banned by CONTENT-GUIDELINES for prose, and this copy is quoted verbatim by AI agents), and an early-cycle "no strong signal" verdict described itself as "mid-cycle", which was simply false for a 10-months-into-36 line. Both now covered by tests.
- **Suite:** 525 feature/unit green (baseline in the plans README, 181, is long stale) + 12 Dusk green. Plan target was +~27 tests; actual is +69.

### Retro code review — 2026-07-27 (the review this plan never got)

All six phases shipped in commit `eee9a02` (2026-07-23) **without a gd-code-reviewer pass** — the only plan in `docs/plans/` that missed its review. Run retrospectively 2026-07-27 against the committed diff. **Verdict: CHANGES REQUIRED (fix-forward).** The engine itself is sound: the verdict matrix was re-derived cell-by-cell and matches the docblock, confidence provably can never read "high" on stale data, the honesty gates propagate (a gated-out `PriceIntel` verdict makes `buy`/`wait_for_price` structurally unreachable), no `/out/` link exists on any cycle page, the em-dash ban is genuinely enforced across 20 position×tier combinations, and 9/10 seeded source URLs verify against their stated dates. Targeted suites re-run green (55 `BuyOrWait*` + 13 `ReleaseCycleTest`).

Open items, in fix order:

1. **BLOCKER (prod) — the seeder was never run.** `release_cycles` is empty on production, so `abort_if($rows === [], 404)` makes `/buy-or-wait` and every `/buy-or-wait/{slug}` a 404, while **four ungated links point at it**: the sitewide footer (every public page), the `/deals` promo paragraph, `/how-we-review#buy-or-wait`, `/for-ai` — plus the `llms.txt` "Key pages" entry advertising the dead URL to crawlers and agents, and a live `get_buy_or_wait_verdict` MCP tool that answers "No release cycles are published yet" to everything. `bin/deploy.sh` does not seed and `DatabaseSeeder` does not register this seeder, so nothing was ever going to do it automatically. **Fix items 2–3 BEFORE seeding** (both defects become publicly visible the moment data exists). Either seed + bust `sitemap.xml` / `search.llms_txt` / the index key + CDN purge, **or** gate the four links on `ReleaseCycle::exists()` — the current state must not persist either way.
2. **BLOCKER (honesty) — the iPhone cadence claim is factually false.** `ReleaseCycleSeeder` line ~67: "Apple has announced a new iPhone in September every year since 2012." The iPhone 12 was announced **13 October 2020**, and the row's `source_url` (the iPhone 17 press release) says nothing about cadence. This string is the feature's highest-amplification text: it renders in the "Release cadence" factor, in the FAQPage JSON-LD, in the MCP `cycle.pattern_note`, and verbatim in the unpublished launch post at `daily-drop/launch/news-buy-or-wait.md`. Per this plan's own top risk ("stale cycle data = wrong advice"), a checkably false absolute is worse than a hedge. Fix both files, then re-seed.
3. **BLOCKER — meta description is missing its unit word.** `BuyOrWaitController::metaDescription()` formats `'%s has been out %d.'`, rendering "iPhone 17 has been out 10." into the SERP snippet of every cycle page. No test asserts `<meta name="description">`, which is why it shipped. Add that assertion with the fix.
4. **WARN — `ReleaseCycle::flagshipProduct()` will silently lose the price half of the verdict.** It pulls `orderByDesc('id')->limit(50)` and name-matches in PHP; for the four `category_id = null` lines that scans the 50 newest reviewed products *site-wide*, so at ~11 posts/week the matching review ages out in roughly six weeks and the page quietly drops to "we do not yet track a product on this line" (MCP `price` block goes null). Push the name match into SQL or drop the cap; order by the review's `published_at`, not `products.id` (the docblock already claims "newest review first", which the code does not do).
5. **WARN — the index cache is never busted by a cycle write.** Per-cycle verdicts self-bust via `updated_at`, but `cachedIndex()` keys only on namespace + date, so the Maintenance promise ("update the row same-week, the verdict flips automatically") holds on the cycle page and fails on `/buy-or-wait` for up to 6h (and sitemap/llms.txt for 24h). Add `ReleaseCycle::max('updated_at')` to the key or a `saved`/`deleted` flush hook mirroring `Post::saved → NavigationData::flush()`. This also deletes the manual `tinker` step from §Deployment above.
6. **WARN — unflagged divergence from §6.3 step 2.** The page renders a bare `@livewire('join-the-drop')` with generic weekly-drop copy, while the Blade comment above it claims "this genuinely is a manual list today, and says so" — it doesn't. Either add the lead-in sentence or delete the misleading comment. Divergence is fine; silent divergence is not.
7. **WARN — contrast.** The index confidence + "cycle data verified" line is `text-xs text-gray-400` on white (≈2.5:1). Note this is calibration, not a missed rule: the contrast convention landed with Plan 10.6 on 2026-07-26, *after* this commit. Move to `text-gray-500`; the closing disclaimer may stay `gray-400`.
8. **NITs.** MCP reverse-containment resolves "iPad Pro 13-inch" to the parent iPad cycle and returns a 24-month cadence for a different family (add a `line_note` or refuse); the strip's "no strong signal" short label loses the late-cycle clearance nuance; `assertLessThanOrEqual(1, $outLinks)` in `BuyOrWaitStripTest` passes at zero links (assert `assertSame(1, …)`); `show.blade.php` renders "What {product} costs right now" even when `has_stats` is false (wording mismatch, not a gate leak); CLAUDE.md documents `<x-buy-or-wait-strip>` but never the `/buy-or-wait` public surface itself.

**Test-coverage gaps that map to the above** (point additions, no `gd-test-engineer` needed): no assertion on the rendered meta description (caused item 3); no test that a name-matching product older than 50 newer reviews is still found (item 4); no test that a cycle write changes the index in the same request (item 5); no test that the sitewide footer link resolves — the suite in fact *encodes* the broken state, asserting the empty index 404s while the footer link is unconditional; no MCP sub-line query test.

**The two decisions still parked on Bryan:** (a) *nav placement* — the reviewer signs off on footer + `/deals` + strip as shipped and agrees the desktop header must stay untouched (it crams at `md`), but suggests a real card in the `/deals` body and a sidebar line, since discovery is thin for what the plan calls its biggest SEO surface; (b) *the base-iPad MacRumors citation* — acceptable as shipped (the documented fallback rule was followed exactly and the dates check out), with a suggestion to add an `apple.com/newsroom/2025/03/` archive or same-day iPad Air link as a corroborating anchor at the quarterly re-verify.
