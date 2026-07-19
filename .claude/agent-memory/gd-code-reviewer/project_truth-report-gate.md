---
name: truth-report-gate
description: TruthReport "judged" honesty gate is span-only by design (not PriceIntel's points+span) — intended, tested, don't re-flag as a bug
metadata:
  type: project
---

`App\Support\TruthReport::classify()` (plan 05 Phase 5.1) gates a product to
`insufficient` purely on span: `trackedSince <= from - config('truth.min_baseline_days')`
(14). Unlike `PriceIntel` it does NOT also require `MIN_POINTS` (≥2 snapshots).

Consequence: a product with a single pre-event snapshot and no event-window
observation carries flat and classifies as `repackaged` (0% discount), and it
enters the public "% real deals" denominator (`totals.judged`).

**Why:** intended under the site's carry-forward stance — snapshots record only
price *changes*, so no event snapshot legitimately means "held" = not a deal.
Documented in `config/truth.php` (comment names MIN_SPAN_DAYS for the value, not
full parity) and covered by tests (`Flat` product → repackaged). The implementer
flagged the `min_baseline_days = 14` choice. So this is a confirmed-safe,
intended design, not a defect.

**How to apply:** do NOT re-BLOCK/WARN this as "missing MIN_POINTS" in reviews of
Phases 5.2–5.4. It is legitimate calibration territory: the 5.4 pilot is where
threshold/gate feel gets tuned. Only escalate if the public page (5.2) presents
the `judged` denominator as "actively verified during the event" without the
denominator-forward honesty copy the plan mandates. See [[canopy-api-budget]]
for the sibling "we grade deals, not Amazon" honesty posture.
