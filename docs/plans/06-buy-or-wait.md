# Plan 06 — Buy-or-Wait Engine

**Goal:** own the empty niche: living verdict pages ("Should I buy AirPods Pro now or wait?") combining editorial release-cycle data with our own price curves. Kakaku.com proves buy-timing is a durable consumer behavior; US search intent is huge and no dedicated tool exists. Formalizes the "## Buy or Wait?" sections the news pipeline already writes, and (final phase) becomes the killer MCP tool.
**Size:** L — the biggest plan; phases 6.1–6.2 are safe foundations, 6.3+ each gets a go/no-go review with Bryan.
**Branch:** `feature/buy-or-wait` · **Depends on:** Plan 01; Plan 04 (for Phase 6.5)
**Non-goals:** rumor aggregation (we cite announced patterns + history, not leaks); per-product release predictions for the long tail (categories first); automated scraping of release news (editorial curation with source URLs, matching the pipeline's SOURCE_URL discipline).

## Phase Log

- [ ] Phase 6.1 — `release_cycles` schema + seeded editorial dataset (commit: )
- [ ] Phase 6.2 — `BuyOrWait` verdict engine (pure, unit-tested) (commit: )
- [ ] Phase 6.3 — Public pages: /buy-or-wait + per-category (commit: ) · **go/no-go with Bryan first**
- [ ] Phase 6.4 — Review-page strip + news cross-links (commit: )
- [ ] Phase 6.5 — MCP tool + llms.txt entry (commit: )
- [ ] Phase 6.6 — Dusk pass + launch content pass (commit: )

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
