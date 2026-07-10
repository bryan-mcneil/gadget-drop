# Plan 05 — Truth Report Pipeline (Prime Day / Black Friday)

**Goal:** a repeatable data-exposé pipeline: for any sale event, crunch our own snapshots into "how many tracked 'deals' were actually deals," publish a permanent data page + an editorial post. Germany's mydealz and Brazil's Black Friday plugins prove the appetite; our voluntary 30-day-reference stance (Plan 01) gives it teeth. Annual traffic ritual + backlink magnet.
**Size:** M · **Branch:** `feature/truth-report` · **Depends on:** Plan 01 (verdict language, methodology page)
**Non-goals:** naming-and-shaming Amazon (we grade *deals*, not the retailer — Associates-safe); real-time event dashboards (post-event analysis is the honest format — our snapshot cadence isn't real-time); tracking products beyond the catalog.

## Phase Log

- [ ] Phase 5.1 — Analyzer + `truth:report` command (commit: )
- [ ] Phase 5.2 — `/truth/{slug}` data page (commit: )
- [ ] Phase 5.3 — Editorial wrapper + content guidelines (commit: )
- [ ] Phase 5.4 — Prime Day 2026 pilot (runbook execution) (commit: )

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

**Scope:** run the machine for real. Prime Day is mid-July 2026 — snapshots are accumulating NOW regardless (the pipeline is retroactive by design).

Runbook (this phase is executed, not coded):
1. Pre/during event: one `/admin/prices` manual pass daily on the most-viewed products (top 20 by `view_count`-linked posts) — provenance `manual` snapshots densify event coverage within $0 budget.
2. After the event: `php artisan truth:report prime-day-2026 --from=… --to=…` (exact dates from Amazon's announced window); review JSON with Bryan; sanity-check 5 products by hand against their snapshot rows.
3. Publish decision: flip config, deploy, editorial post via the normal pipeline, social via the existing outbox flow.
4. Log honest lessons in the Build Log: coverage %, which classification thresholds felt wrong, what Black Friday needs (this pilot's REAL deliverable is calibration for BF, where the traffic is).

Commit: `chore(truth): prime-day-2026 report published` (config + any threshold tuning from review)

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

## Build Log

(append one line per phase)
