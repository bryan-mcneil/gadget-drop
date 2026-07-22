---
name: priceintel-verdict-duplication
description: Plan 03 added PriceIntel::verdictFor() which copies compute()'s inline verdict-tier match — two sites, drift risk; check both on any tier/DEAL_PCT change
metadata:
  type: project
---

`PriceIntel::verdictFor(float $price, ?array $stats)` (added in plan 03 for the closing-window watch mail) re-implements the exact same tier `match` that `PriceIntel::compute()` runs inline for the on-page verdict (`hasVariation && <= low90+0.009 => 'lowest'`, `<= avg90*(1-DEAL_PCT) => 'good'`, `>= avg90*(1+DEAL_PCT) => 'elevated'`, else `'typical'`).

**Why:** The two copies are byte-identical except `$current`→`$price` / `$result`→`$stats`. If a future edit changes the tiers or `DEAL_PCT` in one site only, the closing-mail verdict ("you paid a {verdict} price") would silently disagree with the review-page verdict for the same price — an honesty-drift bug, the same class the codebase warns about for `dailySeries` ("keep the math here rather than copying it"). Related: [[honesty-gate-tri-surface]].

**How to apply:** When reviewing any change to PriceIntel verdict tiers, grep for BOTH `verdictFor` and the inline `$result['verdict'] = match` in `compute()` and confirm they still agree. Cleanest fix (suggest, don't force): have `compute()` call `self::verdictFor($current, $result)` after it sets `has_stats`/`low90`/`high90`/`avg90`, collapsing to one source. Flagged as a WARN in the plan-03 3.2–3.5 review; not yet fixed as of 2026-07-22.
