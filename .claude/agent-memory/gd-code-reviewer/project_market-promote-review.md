---
name: market-promote-review
description: Plan 08 market→catalog promotion — withoutEvents+seeded-history pattern is confirmed-safe; column-width mapping branches are the standing test gap
metadata:
  type: project
---

Plan 08 (`docs/plans/08-market-promote.md`, `MarketPromotionService`) promotes a `market_products` row into the curated `products` catalog, copying `market_price_snapshots` → `product_price_snapshots` with `source='market'` at original dates.

**Confirmed-safe patterns — don't re-flag in 08 follow-ups:**
- `Product::withoutEvents(create)` is deliberate and complete: the ONLY Product model events are `ProductObserver` (via `#[ObservedBy]`). Skipping them suppresses (a) the `saving` hook that would stamp `price_checked_at=now()` and (b) the `created` hook that appends an "observed today" snapshot — both intentionally replaced by seeded history + explicit `price_checked_at=last_seen_at` + explicit `PriceIntel::flush()`. No NavigationData/sitemap/deals cache is Product-create-dependent (nav filters on posts; a just-promoted product has no post yet, so no public surface changes until a review publishes). Same seeding-honesty precedent as the deleted `prices:backfill`.
- `price_checked_at == last_seen_at` assertion (test) doubles as proof the `saving` hook was suppressed — withoutEvents is effectively tested.
- Fallback single snapshot uses `current_price`, which is **NOT NULL** in the market_products schema — no null-price insert risk. `asin` is `char(10) unique` — always present.
- Admin-only route inside `['auth','verified']` group; no throttle needed (throttle invariant is public-only; sibling admin POSTs like prices.update aren't throttled either).
- Admin JSX Amazon `/dp/` links are the pre-existing sanctioned admin-only pattern (comment-marked). The stored `affiliate_url = amazon.com/dp/{ASIN}` is the Product column, not an output surface — routes through `/out/{product}`. See [[raw-amazon-blocker-scope]].
- Flash key `success` is the only key `HandleInertiaRequests` shares — controller uses it correctly.

**Standing test gap (WARN, not blocker):** the "Column-width mapping" design decision (§22) — `name` truncated to 255, over-255 `image_url` **dropped not truncated** (a cut URL 404s), `review_count` capped to unsignedMediumInteger (16,777,215) — has NO test coverage. Logic lives in `MarketPromotionService::promote()`. If a follow-up touches the mapping, push for a `gd-test-engineer` case on the image_url-drop branch specifically.
