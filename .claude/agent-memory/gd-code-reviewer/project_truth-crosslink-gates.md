---
name: truth-crosslink-gates
description: Truth Report cross-linking uses two different publish-count thresholds by design (methodology link ≥1, sitemap /truth index ≥2) — don't flag as inconsistent
metadata:
  type: project
---

Plan 05 Phase 5.3 cross-linking deliberately uses **two different gates** on the same `TruthReport::published()` count — both correct, do not flag as an inconsistency:

- `/how-we-review` "See our Truth Reports →" link renders at **≥1** published report (plan 5.3 step 3: "once the first report publishes"). It targets `route('truth.index')`, which 302-redirects to the single report while only one exists — acceptable, resolves fine under `wire:navigate`.
- Sitemap `/truth` index entry joins only at **≥2** (`SitemapController`), because at 1 report `/truth` 302-redirects and a redirecting URL shouldn't be a canonical sitemap entry. This carried in a 5.2 reviewer NIT the Build Log explicitly deferred to 5.3.

**Why:** the two thresholds each match `TruthReportController::index()`'s own behavior (404 empty → 302 at 1 → list at 2+). See [[truth-report-gate]].

**How to apply:** in 5.3–5.5 reviews, don't raise the differing counts as a bug. Also confirmed-safe: the `/deals` `truthPromo` is computed per-request in `DealsController::index()` **outside** the `Cache::remember('deals.feed', 1h)` wrapper by design (a moment-in-time event pointer must reflect config immediately) — don't flag it as missing cache coverage.
