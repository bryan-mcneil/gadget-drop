---
name: deals-pill-composer
description: Plan 10.4 header Deals pill reuses count(DealsFeed::get()) site-wide via layouts.public composer; trackedCount new 1h key, both flush-hooked — confirmed-safe, don't re-flag as N+1/perf
metadata:
  type: project
---

Plan 10.4 wired the header's live "Deals" count pill by adding `dealsLiveCount = count(DealsFeed::get())` to the **`layouts.public` View composer** in `AppServiceProvider` — so `DealsFeed::get()` now runs on **every public page**, not just `/deals`.

**Why:** the plan's Step 4 checklist demands "nav count query cached on the database store with a sane TTL + flush hook." Reusing the existing `deals.feed` key (1h, db cache, busted by `PriceIntel::flush`) satisfies that with zero new query cost in the steady state — the pill count == the /deals "live now" chip == qualifying-deal count, all one source.

**How to apply:** do NOT re-flag the site-wide composer as an N+1 or shared-hosting perf regression — it is plan-blessed and cached. On a cold cache (hourly expiry or any `PriceIntel::flush`) the first public request site-wide absorbs `DealsFeed::build()` (same query /deals already ran); acceptable, note as a NIT at most. `DealsFeed::trackedCount()` is a SEPARATE new key `deals.tracked_count` (1h, same defensive try/catch), busted on the SAME `PriceIntel::flush()`. Both cache hooks confirmed correct.

Resolved in-phase (was a review NIT): `DealsFeed::countTracked()` now filters `whereHas('posts', fn ($q) => $q->published()->whereNotIn('type', ['tech_tip', 'tech_news']))` — mirroring `build()`'s candidate universe, so "N products tracked" counts exactly the products that could ever surface as a deal (minus the drop gate) and the "published review" docblock is accurate. Don't re-flag the count as review-agnostic.

Confirmed-safe this phase: hero LCP preload untouched (uses `$heroSlides[0]`; only slide tag names changed h1→h2 via `$htag`); deals `<details class="deals-method">` keeps "tracked 90-day average" in the DOM when closed (native details); stat chips each guarded (`trackedCount>0` / `liveCount>0` / `topDrop!==null`) + outer row guard. Related: [[deals-query-count-guard]], [[priceintel-verdict-duplication]], [[wire-navigate-fragment-exception]].
