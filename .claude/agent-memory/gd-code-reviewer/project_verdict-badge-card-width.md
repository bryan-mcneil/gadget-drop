---
name: verdict-badge-card-width
description: Plan 01 — sm x-verdict-badge in the review product-card's ~167px content column can overflow at 375px for long lowest/good+dropPct labels; verdict!==null is an honesty-safe has_stats proxy
metadata:
  type: project
---

Plan 01 (verdicts-everywhere) surface-review calibration.

**sm badge overflow watch (Phase 1.2+):** the review product card (`resources/views/components/product-card.blade.php`) flanks a fixed `w-32` (128px) image, so at 375px its content column is only ~167px (343 page − 32 card p-4 − 128 img − 16 gap). The shared `<x-verdict-badge size="sm">` is `whitespace-nowrap`; the longest label — "Lowest tracked price · NN.N% below typical" (~42 chars @ text-[10px] ≈ 200px) — exceeds 167px and overflows because the badge is a flex item with default `min-width:auto` (the parent's `min-w-0` lets the COLUMN shrink but does NOT clip a nowrap descendant, and no ancestor has overflow-hidden).
**Why:** Plan 01 Risk section (line 116) pre-identified exactly this ("if the sm badge crowds 375px layouts, drop dropPct at sm size"). The prescribed fix is to omit `:drop-pct` at `size="sm"` (label collapses to ~20 chars, fits). `/deals` sm badges are safe by contrast — those cards are full-width single-column on mobile (~311px), not image-flanked.
**How to apply:** on any NEW card surface that renders the sm badge beside an image at a constrained width, re-check the longest label at 375px (console diagnostic in CLAUDE.md, or the Phase 1.5 Dusk pass). Don't accept "flex-wrap + min-w-0 + nowrap" as an overflow guarantee — that combination PRODUCES the overflow. WARN, not BLOCKER (only the lowest/good+dropPct tiers, only ≤375px).

**Honesty-gate proxy (confirmed-safe, don't re-flag):** keying a verdict surface on `$stats['verdict'] !== null` is exactly equivalent to `has_stats === true` — in `App\Support\PriceIntel::compute()` the initial result seeds `verdict => null` and it is only assigned a tier (line ~127) inside the block gated by the MIN_POINTS(2)/MIN_SPAN_DAYS(14) check (line ~101), the same block that sets `has_stats = true` (line ~115). So a chip that renders `@if($verdict !== null)` never shows a verdict without stats. `drop_pct` can be non-null on a `typical`/`elevated` tier (set whenever current<avg90), but `<x-verdict-badge>` only appends the "% below typical" for `lowest`/`good`, so passing `drop_pct` through unconditionally is fine.

See also [[wire-navigate-fragment-exception]] (the `#deal-verdicts` methodology link correctly omits wire:navigate here too, commented) and [[raw-amazon-blocker-scope]] (the test fixture's `affiliate_url => https://www.amazon.com/dp/...` is a column value, not an output surface — not a BLOCKER).
