---
name: cardintel-eager-load
description: Plan 10.3 cardIntel lead-product eager-load — belongsToMany limit(1) is confirmed-safe on sqlite AND required (not just perf); don't re-flag
metadata:
  type: project
---

`PublicController::cardIntel()` (Plan 10.3, listing card price+verdict row) reads
the post's lead product via `$post->products->first()` after the listing eager-loads
`->with(['products' => fn ($q) => $q->orderBy('display_order')->limit(1)])`.

- **The `limit(1)` on a belongsToMany eager-load is per-parent, not global** — Laravel
  11+ compiles eager-load limits as a window function (`PARTITION BY` the pivot FK).
  Works on sqlite (`:memory:` tests) — proven green by `PublicPagesTest` category +
  home verdict-chip tests and the pre-existing Top Picks (`home()`) use of the same
  closure. Confirmed-safe: **don't re-flag the eager-load limit as globally-limited.**
- **The eager-load is REQUIRED, not merely N+1 hygiene.** `cardIntel()` guards on
  `! $post->relationLoaded('products')` and returns the blank row if products aren't
  loaded — so WITHOUT the eager-load no verdict chip ever renders (silent no-op), not
  a lazy-load N+1. If a future listing adds `<x-post-card>` with verdict rows, it must
  add the same eager-load or the price/verdict row silently vanishes.
- `->orderBy('display_order')` in the closure orders by the **pivot** column (products
  table has no such column); unambiguous, resolves correctly.

**Why:** the user flagged this eager-load as a plan divergence (the 10.3 checklist wrongly
said "posts already eager-load products"); it's honest, correct, and load-bearing.
**How to apply:** treat this `products`-limit-1 eager-load pattern as confirmed-safe on
sqlite; when reviewing a new `cardIntel`-style gated row, verify the source query carries
the eager-load or the feature is dead. Related: [[priceintel-verdict-duplication]],
[[verdict-badge-card-width]].
