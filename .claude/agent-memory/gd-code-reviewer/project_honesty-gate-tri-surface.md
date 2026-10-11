---
name: honesty-gate-tri-surface
description: The ≥2-snapshots/≥14-days honesty gate wording now lives in three surfaces that must stay in sync; where each is safe vs drift-prone
metadata:
  type: project
---

The PriceIntel honesty gate (`MIN_POINTS=2`, `MIN_SPAN_DAYS=14`, `DEAL_PCT=0.05`) is now stated as user/agent-facing prose in three places. When any phase edits deal-verdict copy, cross-check all three read the same numbers.

1. `app/Support/PriceIntel.php` — the source of truth (constants).
2. `resources/views/public/how-we-review.blade.php` #deal-verdicts (Plan 01 §1.4) — pulls the constants via an `@php` block (`$gateMinPoints` etc.) and renders the real `<x-verdict-badge>` for each tier. **Drift-proof**: numbers AND labels are pulled from code, and `VerdictSurfacesTest` pins the constant-derived substrings.
3. `app/Mcp/GadgetDropServer.php` instructions — states the gate as **spelled-out words** ("at least two price snapshots spanning at least fourteen days"). **Drift-prone**: hardcoded English, not pulled from constants. If `MIN_POINTS`/`MIN_SPAN_DAYS` ever change, this string won't follow — flag it in any phase that touches the gates.

Confirmed 2026-07-19 (Phase 1.4 review): all three agreed (2 / 14 / 5%). Also confirmed-safe and don't re-flag:
- `PriceIntel::DEAL_PCT * 100` evaluates to an exact `float(5)` and echoes as `"5"` (no `5.0000001` fragility); the blade `@php` var and the test share the expression so they can't diverge.
- The four-verdict copy on the page matches `PriceIntel::compute()`'s match() exactly (lowest needs `hasVariation` + at-low; good = `<= avg*0.95`; elevated = `>= avg*1.05`; typical = default).
- The FAQ JSON-LD Q1/Q3 hand-type the badge labels ("Lowest tracked price"/"Typical price") rather than pulling from the component — minor label-drift risk, but a rename would break the visible-section test first and force a fix. NIT-level only; plan §1.4 required only *numbers* pulled.

See also [[verdict-badge-card-width]], [[mcp-server-invariants]].
