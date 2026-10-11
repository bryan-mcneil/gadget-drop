---
name: market-import-review
description: market:import (prancy-lollipop plan) confirmed-safe patterns + two intentional deviations — don't re-flag in follow-on phases
metadata:
  type: project
---

`market:import` / MarketImportService / MarketProduct+MarketPriceSnapshot layer (approved plan `for-gadget-drop-a-prancy-lollipop.md`, reviewed 2026-07-21, no blockers).

Confirmed-safe patterns — do NOT re-flag on this feature's future phases:
- **2 grouped queries per chunk, not the plan's 3.** Service compares new price to `market_products.current_price` instead of re-querying the latest snapshot. Sound because snapshots are change-only, so latest-snapshot-price ALWAYS equals current_price. Documented in `updateKnown` docblock. Strictly fewer queries; correct.
- **`market_products.url` stores raw amazon.com URLs.** It is a DATA column, not an output surface — nothing renders it. Reinforces [[raw-amazon-blocker-scope]]. Only becomes a `/out` violation if a future public/MCP surface renders it (then it must route through `route('affiliate.redirect')`, `rel="nofollow sponsored"`).
- **`first_seen_at`/`last_seen_at` in `$fillable` (MarketProduct).** No HTTP mass-assignment vector — importer is CLI/file-only and builds explicit arrays. `MarketPriceSnapshot.created_at` fillable mirrors ProductPriceSnapshot's documented rationale (scrape-dated rows). Acceptable exception to [[fillable-state-columns]].
- **Observer $source set-then-finally-reset** in `mergeToCurated` is correct (finally guarantees 'import' never leaks onto later 'manual' saves); mirrors RefreshProductPrices exactly. Stale guard skips only when `price_checked_at !== null` (a never-checked product can't be stale).

Two INTENTIONAL, sound deviations from the plan (both documented) — but each has a test-coverage gap worth closing:
- **`brand` optional column added** (schema already had the column). Never exercised by any test (test HEADER const omits brand) — parse/store/meta-merge-guard unverified.
- **top-level `warnings` key** added to report between `rejects` and `ignored_columns`. Stable-key-order test asserts the KEY exists, but NO test drives a warnings count > 0 (invalid list_price/rating/url/scraped_at → nulled + row still imports). Behavior unasserted.

**Why:** these calibrate follow-on reviews so I don't re-litigate settled decisions and can point precisely at the two open test gaps.
**How to apply:** on the next market-layer phase, if it adds a brand/warnings test or a public surface for market data, check the url-routing rule; otherwise treat the above as resolved.
