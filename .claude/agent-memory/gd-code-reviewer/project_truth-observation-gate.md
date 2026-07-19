---
name: truth-observation-gate
description: Plan 05 §5.5 unobserved gate is correct, but confirm-unchanged price paths record no snapshot — a BF honesty risk to watch, not a §5.5 defect
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

**How to apply:** at BF/CM report phases, verify this was resolved (Phase 5.6 or a Risks
entry in `docs/plans/05-truth-report.md`) before trusting `unobserved` counts. If still
open, the BF report's `unobserved` bucket will include genuinely-watched flat products.
Sibling: [[truth-report-gate]] (the span-only `insufficient` gate, separate and intended).
