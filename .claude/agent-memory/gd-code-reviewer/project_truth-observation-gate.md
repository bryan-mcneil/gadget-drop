---
name: truth-observation-gate
description: Plan 05 §5.5 unobserved gate is correct; the confirm-unchanged no-snapshot gap it flagged is now RESOLVED by the durable confirmed-check mechanism (2026-07-18)
metadata:
  type: project
---

Plan 05 Phase 5.5 added `TruthReport::classify()` `unobserved` classification: a
product with a valid baseline but NO snapshot recorded inside `[from, to]` is not
judged (`$snapshots->contains(date in window)` gate). Correct + well-tested; the
2026 Prime Day pilot's 10 carry-forward-only "repackaged" verdicts now report as
`unobserved`. Do NOT re-flag the gate itself.

**Non-obvious cross-cutting gap (deferred at 5.5, agreed):** the same-price/
confirm-unchanged paths record NO snapshot row — `ProductObserver::snapshotIfChanged`
returns early when price is unchanged; `Admin/PriceController::confirm()` and
`::update()` same-price branch only `forceFill(price_checked_at)`; `RefreshProductPrices`
unchanged branch likewise. So a product that WAS actively checked during an event but
held flat leaves no in-window snapshot → it classifies `unobserved`, undercounting the
`repackaged` story and potentially inflating the apparent real-deal share among judged.

**Why it matters:** §5.5's own runbook (from 5.4) recommends daily `/admin/prices`
"confirm unchanged" passes during the event — which is exactly the action that produces
zero in-window snapshots for flat products. The fix (a confirmation-snapshot mechanism)
is a snapshot-recording semantics change, genuinely outside §5.5 (classify + tests +
page) and needs Bryan's design call.

**RESOLVED 2026-07-18 (Bryan-ordered ad-hoc fix, branch feature/truth-report, reviewed
APPROVE):** `ProductObserver::updated()` gained an `elseif ($product->wasChanged('price_checked_at'))`
→ `snapshotConfirmedCheck()`: a check-but-unchanged save now writes a same-price
`ProductPriceSnapshot` (source = `self::$source`, so admin=manual, API refresh=canopy/pa_api)
+ `PriceIntel::flush()`, capped at ONE row per product per day (`$latest->created_at->isToday()`
guard). Fixes both the `unobserved` undercount AND the stale 6h-cached "Price checked" label
(flush now fires on confirm). Prod stamp path itself was never broken (35/35 checked <48h);
fix was about durable rows + cache flush.

**Confirmed-safe — do NOT re-flag as a "lowest"-fabrication honesty risk:** same-price
confirmed-check rows can now satisfy PriceIntel's ≥2-snapshots/≥14-day gate with a perfectly
FLAT series. That is fine: `PriceIntel::compute()` guards `verdict='lowest'` behind
`$hasVariation = (high90 - low90) > 0.009`, so a flat series → `typical`, never `lowest`;
`drop_pct` stays null (current==avg90) so `DealsController` (needs verdict in lowest/good AND
drop_pct≥5) can never surface it. Regression-covered transitively by
`PriceHistoryWidgetTest::test_verdict_math` (flat 2-point → typical) + DealsPageTest gate tests.

**How to apply:** the `unobserved` bucket is now trustworthy for BF/CM reports as long as
the daily `/admin/prices` "confirm unchanged" passes actually ran during the event window
(each produces one in-window snapshot for flat products). Sibling: [[truth-report-gate]]
(the span-only `insufficient` gate, separate and intended).
