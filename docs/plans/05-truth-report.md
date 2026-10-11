# Plan 05 — Truth Report Pipeline (Prime Day / Black Friday)

**Goal:** a repeatable data-exposé pipeline: for any sale event, crunch our own snapshots into "how many tracked 'deals' were actually deals," publish a permanent data page + an editorial post. Germany's mydealz and Brazil's Black Friday plugins prove the appetite; our voluntary 30-day-reference stance (Plan 01) gives it teeth. Annual traffic ritual + backlink magnet.
**Size:** M · **Branch:** `feature/truth-report` · **Depends on:** Plan 01 (verdict language, methodology page)
**Non-goals:** naming-and-shaming Amazon (we grade *deals*, not the retailer — Associates-safe); real-time event dashboards (post-event analysis is the honest format — our snapshot cadence isn't real-time); tracking products beyond the catalog.

## Phase Log

- [x] Phase 5.1 — Analyzer + `truth:report` command (commit: 9b9149d)
- [x] Phase 5.2 — `/truth/{slug}` data page (commit: 3b39178)
- [x] Phase 5.3 — Editorial wrapper + content guidelines (commit: 59beb9a)
- [x] Phase 5.4 — Prime Day 2026 pilot (runbook execution) (commit: none — no-publish decision; see Build Log)
- [x] Phase 5.5 — Event-observation gate (pre-BF, from pilot lessons) (commit: ab0604b)

## Design decisions

- **Post-event, from stored snapshots.** `product_price_snapshots` records every change with provenance; the analysis is a pure read. A report can be generated retroactively for any window — the Prime Day pilot works even though the event may already have started.
- **Classifications per product** (thresholds in `config/truth.php`):
  - `real_deal` — event-window min ≤ pre-event 30-day min × 0.95
  - `repackaged` — event min within ±5% of pre-event 30-day min (the "sale price" was just… the price)
  - `worse` — event min > pre-event 30-day min × 1.05
  - `insufficient` — honesty gate: not enough pre-event history to judge (reported, counted, never guessed)
- **Artifacts are JSON first** (`storage/app/truth/{slug}.json`): headline stats + per-product rows + config used + generated_at. The page renders the JSON; the post quotes it; next year diffs it. Reports are re-runnable and auditable.
- **Small-n honesty:** the headline always states the denominator ("of the 61 products we track…"). If tracked coverage of event deals is thin, the report says so — that IS the story ("we could only verify N — here's why that matters").

---

## Phase 5.1 — Analyzer + `truth:report` command

**Scope:** `app/Support/TruthReport.php` (pure analysis, `ArticleBody`-style: no hard facade dependencies — unit-testable without full boot where feasible; DB access via models is fine) + `app/Console/Commands/GenerateTruthReport.php`.

Steps:
1. `TruthReport::analyze(CarbonInterface $from, CarbonInterface $to): array` — for each product with any snapshot: pre-window baseline (30d before `$from`: min/avg via the same carry-forward logic as PriceIntel — REUSE `PriceIntel::dailySeries` by promoting it from private to a small public/internal seam rather than copying math; keep one source of price-series truth), event-window min, classification, deltas. Aggregate: counts + percentages per class, biggest real deal, biggest markup, median event discount vs claimed-feel.
2. Command `truth:report {slug} {--from=} {--to=}`: validates dates, runs analyzer, writes `storage/app/truth/{slug}.json` (pretty-printed, stable key order for clean git-less diffing), prints the headline block to console. `--dry-run` prints without writing.
3. `config/truth.php`: thresholds above + `publish` map (`slug => ['title' => …, 'published' => bool]`) — publication is config-explicit, never automatic.

Tests: unit-style feature tests (`tests/Feature/TruthReportTest.php`) with seeded snapshot fixtures + `Carbon::setTestNow`: each classification branch; carry-forward correctness at window edges (snapshot before window carries in); insufficient-history gate; aggregate math; command writes valid JSON + dry-run writes nothing.

Commit: `feat(truth): snapshot analyzer + truth:report command`

Review checklist: PriceIntel seam refactor doesn't change its behavior (existing PriceIntel tests stay green untouched); JSON schema documented in a comment block; no network calls anywhere; thresholds only from config.

## Phase 5.2 — `/truth/{slug}` data page

**Scope:** route + controller + Blade rendering a published report.

Steps:
1. Route `GET /truth/{slug}` → `TruthReportController@show`: 404 unless slug exists in `config('truth.publish')` with `published => true` AND the JSON file exists. `GET /truth` index listing published reports (404s gracefully to a single-report redirect while only one exists — keep simple: build the index page from config).
2. Blade `resources/views/public/truth/show.blade.php`: headline stat hero ("X% of the deals we tracked this Prime Day were real"), class breakdown bars (pure CSS/Tailwind, no chart lib), best/worst tables (product name → linked to review page; NO affiliate links on this page — editorial integrity surface, the review holds the CTA), methodology box linking `/how-we-review#deal-verdicts`, denominators + `generated_at` prominent.
3. serverMeta per report (title/description from config + JSON stats) + `serverJsonLd`: `Dataset` schema (name, temporalCoverage, creator) — answer-engine catnip. Add published reports to the sitemap (follow `SitemapController` patterns + `SitemapTest`).
4. OG image: reuse the existing `OgImageController` pattern with a stat-callout variant if cheap; otherwise default OG — decide at review, don't gold-plate.

Tests (`tests/Feature/TruthPageTest.php`): unpublished slug 404s; published renders headline + denominator; zero affiliate links on the page (assert); sitemap includes published only; JSON-LD Dataset present.

Commit: `feat(truth): public /truth/{slug} data pages with Dataset schema`

Review checklist: publication gate is config + file (two keys to turn); page never recomputes (renders stored JSON only); noindex NOT set (these pages are meant to rank); mobile table overflow handled (`overflow-x-auto` wrapper, the sanctioned kind).

## Phase 5.3 — Editorial wrapper + content guidelines

**Scope:** the human layer around the data.

Steps:
1. `CONTENT-GUIDELINES.md`: add a "Truth Report" post type section — tone rules: grade deals not Amazon; every claim cites the data page; no outrage-bait headlines (the number IS the headline); always include the "what to do about it" service section (link /deals, the methodology, Join the Drop).
2. Post template: the report post is a normal pipeline `tech_news`-style post (created via the normal admin/import flow — NOT a new post type; sqlite enum lesson) whose body quotes 3–4 stats and links the `/truth/{slug}` page as canonical data source.
3. Cross-linking: methodology page gains a "See our Truth Reports" link once the first report publishes; `/deals` intro links the latest report during event weeks (config-driven line, `truth.publish` already knows).

Tests: none code-wise beyond a `PublicPagesTest`-style assertion that the /deals conditional line renders when config says so (feature-flag test both ways).

Commit: `feat(truth): editorial guidelines + cross-linking for truth reports`

Review checklist: no new posts.type value anywhere; /deals line off by default; guidelines reviewed by Bryan (editorial voice is his).

## Phase 5.4 — Prime Day 2026 pilot (runbook execution)

**Scope:** run the machine for real. Prime Day is mid-July 2026 — snapshots are accumulating NOW regardless (the pipeline is retroactive by design). *(2026 reality check, post-pilot: Amazon ran the event June 23–26 — before the snapshot layer existed. See Build Log.)*

Runbook (this phase is executed, not coded):
1. Pre/during event: one `/admin/prices` manual pass daily on the most-viewed products (top 20 by `view_count`-linked posts) — provenance `manual` snapshots densify event coverage within $0 budget.
2. After the event: `php artisan truth:report prime-day-2026 --from=… --to=…` (exact dates from Amazon's announced window); review JSON with Bryan; sanity-check 5 products by hand against their snapshot rows.
3. Publish decision: flip config, deploy, editorial post via the normal pipeline, social via the existing outbox flow.
4. Log honest lessons in the Build Log: coverage %, which classification thresholds felt wrong, what Black Friday needs (this pilot's REAL deliverable is calibration for BF, where the traffic is).

Commit: `chore(truth): prime-day-2026 report published` (config + any threshold tuning from review)

## Phase 5.5 — Event-observation gate (pre-BF calibration fix) *(added 2026-07-18 from the 5.4 pilot)*

**Scope:** `TruthReport::classify()` + tests + page grade handling. The pilot judged 10 products "repackaged" purely from carry-forward — zero snapshots existed inside the event window. Carry-forward flatness must never masquerade as an event verdict. **Must land before Black Friday.**

Steps:
1. `classify()`: a product with a valid baseline but NO snapshot actually *recorded* inside `[from, to]` is not judged — new classification `unobserved` (distinct from `insufficient`: the history was fine; the event wasn't watched). Carry-forward still fills gaps *between* event-window snapshots; it just can't be the only event source.
2. JSON: `totals` gains `unobserved`; `classes` stays judged-only; schema docblock updated. Re-running prime-day-2026 after this change must yield Judged 0 / Unobserved 10 (the acceptance test for the gate).
3. Page: grades map gains `unobserved` ("Not observed during event", gray); denominator ¶ counts it; the empty-scoreboard hero copy must be generalized — it currently claims "none had enough pre-event history", which is wrong for the unobserved case.
4. Decide at implementation: distinct table styling for `unobserved` vs `insufficient` (lean: distinct label, same muted treatment — they're different honesty stories).

Tests: baseline-ok-but-no-event-snapshot → unobserved; one event snapshot → judged with carry-forward across the rest of the window; totals math includes unobserved; page renders the new label; empty-scoreboard branch still keys on judged === 0; existing fixtures that relied on carry-forward-only judging flip deliberately.

Commit: `feat(truth): event-observation gate — carry-forward can't judge an unwatched event`

Review checklist: no existing test weakened silently; the honesty-gate comment block in `config/truth.php` updated; `TruthPageTest` empty-scoreboard fixture updated to the generalized copy.

## Testing summary

Feature: ~14 new tests (analyzer branches, command, pages, gates). E2E: none needed (static pages; Dusk adds nothing over feature tests here). Suite target: +~14 green.

## Deployment

Standard template + deltas: no migrations; `storage/app/truth/` must exist server-side with write perms (deploy.sh untouched — `storage/` already writable; verify once); JSON files are NOT in git — they're generated on the server (run the command over SSH) or generated locally and `scp`'d — decide at pilot, log it. Publication = config change + deploy + CDN purge.

## Maintenance

- Per event (Prime Day July, early-access October event if any, BF/CM November): run the 5.4 runbook. Calendar entries in README maintenance table.
- Keep every JSON forever (year-over-year is the compounding asset: "vs last year, real deals fell 8 points").
- Annual legal-tone pass on live report pages (same as Plan 01's copy audit).
- `/gd-health` pre-event check (config has next event? snapshot coverage healthy?) each June + October.

## Risks

- **Thin coverage undermines the headline** — mitigated by the denominator-forward copy rule and the pilot's manual-snapshot densification; if coverage < ~30 products, publish as "field notes" post without the standalone page (decision gate in 5.4 step 3).
- **Perceived Amazon hostility** — tone rules in 5.3; grade deals, cite data, offer the service angle. Keepa/CCC have surfaced identical truths for a decade without Associates trouble.
- **Unchanged-price checks are invisible to the observation gate** *(found in 5.5)* — every unchanged-price path (`/admin/prices` "Unchanged" and same-price submit, `prices:refresh` unchanged branch) stamps only the mutable `price_checked_at`; no snapshot row is written, so "checked during the event, price held flat" is retroactively indistinguishable from "never checked" and classifies `unobserved` — undercounting the repackaged story, BF's likely headline. **Resolved same-session** (Bryan-ordered, commit fb0bd5b): `ProductObserver` now records a checked-but-unchanged price as a same-price snapshot (max one per product per day, provenance kept, PriceIntel flushed) — every event-week check is durable observation evidence.

## Build Log

(append one line per phase)

- 2026-07-18 · Phase 5.1 · Built `TruthReport::analyze()` (pre-event baseline vs event-window min over the carry-forward series via the promoted `PriceIntel::dailySeries($snapshots, $start, $end)` seam — explicit window because truth windows are retroactive; sole internal caller passes the identical window, all 37 existing price tests untouched), `truth:report {slug} --from --to [--dry-run]` writing stable-key-order JSON to `storage/app/truth/{slug}.json`, and `config/truth.php` (thresholds 0.95/1.05; `min_baseline_days=14` mirrors `PriceIntel::MIN_SPAN_DAYS` — the plan left the gate unquantified). Suite 270→282 green. gd-code-reviewer: APPROVE WITH NITS, 0 blockers (applied the zero-price-baseline test; **deferred to 5.4 calibration:** the span-only gate counts carry-forward-only products as judged — intended carry-forward stance, revisit after the pilot). Demo dry-run vs local prod-copy DB (guessed window 07-07→07-10): Tracked 35 / Judged 15 / 0 real deals / 11 repackaged / 4 worse / Echo Dot Max +53.9% — judged n=15 is under the ~30 "field notes" bar (Risks §), so event-week `/admin/prices` densification matters. Note for 5.2: the `local` disk roots at `storage/app/private`, so the artifact is written via `storage_path('app/truth')` directly — read it the same way.
- 2026-07-18 · Phase 5.2 · Built `TruthReportController` (+`/truth`, `/truth/{slug}` routes), `truth/show` + `truth/index` Blade views, Dataset JSON-LD, and sitemap entries for published reports (0.8/yearly). Pages render the stored artifact only, double-gated by `TruthReport::published()` (config flag AND file — new `path()/published()/load()` statics shared by command/controller/sitemap); copy quotes the artifact's stored thresholds, never live config (tested). Index: 404 empty → 302 while a single report exists → list at 2+. Added the missing `id="deal-verdicts"` anchor to /how-we-review (plan linked an anchor that never existed); that one link skips `wire:navigate` (native fragment scroll — divergence recorded in reviewer memory). OG: default image (stat-callout variant failed the "if cheap" bar). Suite 282→292 green. gd-code-reviewer: APPROVE WITH NITS, 0 blockers (applied the all-insufficient empty-scoreboard test + stored-vs-live-config proof). **For 5.4 runbook:** sitemap XML caches 1 day — publication deploy needs `Cache::forget('sitemap.xml')` or accepts the delay; `/truth` index joins the sitemap once a 2nd report exists (deferred to 5.3/5.4).
- 2026-07-18 · Phase 5.4 · Pilot executed — **decision: NO PUBLISH.** Prime Day 2026 ran **June 23–26** (Amazon moved it out of July; the plan's mid-July premise was wrong) and the snapshot layer was born 2026-07-02 — **zero snapshots exist inside the event window**, verified identically on prod via read-only tinker (0 event-window rows, 15 pre-event, 10 past the judged gate). `truth:report` on the local prod-copy (bit-equivalent for this retroactive window — the event predates the 2026-07-10 restore): Tracked 35 / Judged 10 / Insufficient 25 / **100% "repackaged", median 0.0%** — pure carry-forward of each judged product's single backfilled snapshot; hand-recomputed all 10 rows from raw snapshot rows, 10/10 exact (analyzer correct, input empty). Judged-10 is under the ~30 field-notes bar AND judged>0 means the page would render a confident scoreboard for an unwatched event — the 5.1-deferred carry-forward hazard, demonstrated on real data → **Phase 5.5 added (event-observation gate; must land before BF).** Other lessons: BF-week manual `/admin/prices` densification is existential (Canopy 3/day cannot cover an event); prod snapshot timestamps read +6h vs the restored local copy of the same rows — verify UTC consistency before BF (midnight-boundary bucketing risk); artifact provenance (Deployment § "decide at pilot"): generate server-side over SSH post-deploy — local generation only proved equivalent because the window predated the restore. Window variants 06-23→06-26 vs →06-27 (UTC tail) byte-identical. Artifact kept locally only (`storage/app/truth/prime-day-2026.json`, gitignored); nothing deployed; prod untouched beyond read-only queries. Thresholds (±5%) never exercised — BF is their first real test. A field-notes editorial post ("we built the machine, it told us we couldn't judge honestly") stays open as a 5.3-dependent option.
- 2026-07-18 · Phase 5.3 · Added the CONTENT-GUIDELINES "Truth Report posts" section (tech_news variant via /admin — never a new posts.type; five tone rules incl. the required Buy-or-Wait service close) and the cross-links: /how-we-review "See our Truth Reports →" gated on `TruthReport::published() !== []`, /deals event-week promo line via new `truth.promote_on_deals` config (null/off default + publication double-gate; computed outside the 1h deals.feed cache so config flips show immediately), and the 5.2-deferred sitemap NIT (`/truth` index joins only at ≥2 published reports). Suite 292→297 green, Pint clean, build verified-then-reverted. gd-code-reviewer: APPROVE WITH NITS, 0 blockers (declined: plural label over the single-report redirect — the plan's own wording; subsumed promo-gate test branch). Bryan approved guidelines voice at commit. Plan 05 code phases now complete; 5.5 (event-observation gate) is the remaining pre-BF item, and the Deployment section is actionable once Bryan wants the branch live.
- 2026-07-18 · Phase 5.5 · Event-observation gate landed: `classify()` returns `unobserved` when a valid baseline has zero snapshots recorded inside the event window (pre-event stats kept, event columns null); totals gains `unobserved`, the headline/median filter tightened to the judged classes (an unobserved null discount could otherwise corrupt the median), and the page/meta copy generalized past the "not enough history" claim. Acceptance per spec: the prime-day-2026 re-run flips Judged 10 → **Judged 0 / Unobserved 10**. Suite 297→299 green, Pint clean, build verified-then-reverted. gd-code-reviewer APPROVE WITH NITS, 0 blockers (applied: unobserved-row pre-event-stat render assertion; declined: zero-count copy pluralization). The reviewer WARN — unchanged-price checks stamp only the mutable `price_checked_at`, leaving no durable observation — became a same-session Bryan-ordered follow-up, `fix(prices)` commit fb0bd5b: checked-but-unchanged prices now append a same-price snapshot (max 1/product/day, source provenance kept, PriceIntel flushed so the 6h-cached "Price checked" label refreshes; prod verified read-only that the stamp itself always persisted — the perceived bug was the stale cached label + queue re-sort). Honesty locked by test: a flat confirmation-built history stays "typical", never "lowest"/deals-eligible. Suite at 301 after the fix. **Plan 05 fully complete.** Deployment note: prod keeps stamping without snapshots until this branch ships — deploy sooner to accumulate BF-worthy observation history.
