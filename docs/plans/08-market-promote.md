# Plan 08 — Market → Catalog Promotion

**Goal:** a market-layer row (`market_products`) can be promoted into the curated catalog (`products`) in one click, carrying its full observed price history with it — so the day a market product earns a review, its PriceIntel gates (sparkline, verdict, /deals eligibility) are already open instead of starting a 14-day clock from zero.
**Size:** S · **Branch:** main (no feature branches — 2026-07-18 convention) · **Depends on:** — (market layer already on main)
**Non-goals:** merging the two tables (separation is load-bearing — `products` is the "review-backed, vetted" contract a dozen surfaces query); a CLI promote command; seeding market history into a product that already exists (future, if ever needed); any change to `market:import` or public surfaces.

Context for the implementer: the two layers connect by ASIN only. `MarketImportService::mergeToCurated()` already pushes prices into curated products whose ASIN matches — the missing piece is *creating* that curated product from a market row without retyping fields and without discarding the market snapshot history. `product_price_snapshots.source` is a plain `string(32)` (NOT an enum — no migration, no sqlite CHECK trap).

## Phase Log

- [x] Phase 8.1 — `MarketPromotionService` + promote endpoint + tests (built 2026-07-22; commit `3a16fb0` — one combined 8.1–8.2 commit; reviewed by gd-code-reviewer, standing WARN recorded in agent memory)
- [x] Phase 8.2 — Admin UI (promote panel, catalog badges) + docs + build (built 2026-07-22; commit `3a16fb0`) — **plan complete; Deployment section actionable**

## Design decisions

- **Insert-with-history, never a move.** The market row stays where it is and keeps accumulating the wide history. Promotion creates the curated `Product` and *copies* the ASIN's `market_price_snapshots` into `product_price_snapshots`. From then on the existing ASIN merge keeps the curated price current on every import.
- **Seeded snapshots are honest.** Each copied row keeps its **real observation date** (`created_at` from the market snapshot) and gets `source = 'market'` so provenance is distinguishable forever. These are genuine price observations, so letting them open the PriceIntel honesty gates (≥2 points spanning ≥14 days) is legitimate — same precedent as the deleted `prices:backfill` seeding from Drop Price puzzle history.
- **`Product::withoutEvents()` on create — deliberately.** `ProductObserver::created()` would write an "observed today" snapshot, but promotion is not a price observation; the last real sighting was `last_seen_at`. The seed *replaces* the observer's initial snapshot (it already ends at the current price, correctly dated), and `price_checked_at` is set to the market row's `last_seen_at` so the public "Price checked {date}" label tells the truth. `PriceIntel::flush()` is called explicitly since the observer path is skipped.
- **Canonical affiliate URL.** `affiliate_url = https://www.amazon.com/dp/{ASIN}` — never the scraped `url` (tracking params; `market_products.url` is data, per docs/MARKET-IMPORT.md). Tag appended at `/out/{product}` redirect time as always.
- **Duplicate guard.** Promotion is blocked when a curated product with the same ASIN exists (validated in the controller, re-checked in the service). The UI shows an "In catalog" state with a link instead of the button.
- **The edit pass reuses the existing product form.** On success, redirect to `/admin/products/{id}/edit` — no field duplication in a new React form. Category is the one input collected up front (market categories are freeform scrape strings; the site has 6 fixed ones).
- **Column-width mapping:** `title` → `name` truncated to 255; `image_url` longer than the products column (255; market allows 500) is **dropped, not truncated** (a cut URL 404s); `review_count` capped to `unsignedMediumInteger`.

---

## Phase 8.1 — `MarketPromotionService` + promote endpoint + tests

**Scope:** new service class, `POST /admin/market-products/{market_product}/promote` route + controller method, feature tests. No UI yet (endpoint is testable without it).

Steps:
1. `app/Services/MarketPromotionService.php` — `promote(MarketProduct, ?int $categoryId): Product`, transaction-wrapped: duplicate-ASIN guard → `Product::withoutEvents(create)` with the field mapping above → bulk-insert seeded snapshots (`source='market'`, original `created_at`, ordered oldest-first; fallback to a single row at `last_seen_at` if the market row somehow has none) → `PriceIntel::flush($product->id)`.
2. `MarketProductController::promote()` — validate `category_id` (`nullable|exists:categories,id`), redirect back `withErrors(['promote' => …])` when the ASIN is already curated, else call the service and redirect to `route('admin.products.edit', $product)` with a success flash.
3. Route in `routes/web.php` next to the market-products resource: `Route::post('market-products/{market_product}/promote', …)->name('market-products.promote');` (inside the admin group).

Tests (`tests/Feature/Admin/MarketPromotionTest.php`):
- Promote requires auth.
- Promote creates the product with mapped fields (name/brand/asin/dp-URL/price/rating/review count/description/category), `price_checked_at == last_seen_at`, and seeds exactly the market history — correct count, all `source='market'`, original dates preserved, **no** extra observer snapshot.
- Blocked when the ASIN is already curated: error on `promote`, no product/snapshots created.
- Market row with zero snapshots seeds one fallback row at `last_seen_at`.
- Seeded history spanning ≥14 days → `PriceIntel::stats()` returns `has_stats=true` with a verdict (the whole point of the feature, asserted).
- Invalid `category_id` → validation error, nothing created.

Commit: `feat(market): promote market products into the curated catalog with seeded history`

Review checklist: transaction covers create+seed; withoutEvents scope is minimal (just the create); no snapshot dated "now"; `PriceIntel::flush` called; affiliate URL is the canonical /dp/ form.

## Phase 8.2 — Admin UI + catalog badges + docs

**Scope:** promote panel on the market-product Edit page, "In catalog" badges on the Index, controller props, doc updates, `npm run build`.

Steps:
1. `MarketProductController::edit()` — add `curated` (id+name of the matching catalog product, or null) and `siteCategories` (`Category::orderBy('name')->get(['id','name'])`) props.
2. `MarketProductController::index()` — after paginating, one `whereIn('asin', …)` query marks each row `curated: bool`.
3. `Edit.jsx` — right-column panel: when `curated`, a green "In catalog" card linking to the product; otherwise a category `<select>` + "Promote to catalog" button (own `useForm`, posts to the promote route) + a two-line explainer (copies history, future imports keep price current). Literal Tailwind classes only (purge).
4. `Index.jsx` — small "In catalog" chip in the title cell (flex + `min-w-0` + truncate per responsive conventions).
5. Docs: `docs/MARKET-IMPORT.md` gains a "Promoting into the catalog" section; CLAUDE.md market-layer bullet gets one sentence; plans README row.
6. `npm run build` to verify JSX compiles (bundle churn: verify-then-revert; rebuild ships with the release commit).

Tests (extend `tests/Feature/Admin/MarketPromotionTest.php`):
- Edit screen exposes `siteCategories` and `curated=null` for an unpromoted row; `curated.id` after a catalog product with that ASIN exists.
- Index marks curated rows `curated=true`, others `false`.

Commit: `feat(market): admin promote panel + in-catalog badges`

Review checklist: no raw Amazon link added beyond the existing admin-only pattern; badge cell keeps `min-w-0`/truncate discipline; promote button hidden (not just disabled) when already curated; flash key is `success` (the only one `HandleInertiaRequests` shares).

## Deployment

Shared template (plans README) — deltas: no migration, so `migrate --force` is a no-op for this plan. After deploy: hard-refresh `/admin/market-products`, promote a real market row end-to-end, confirm the product lands in `/admin/products` with its sparkline live on the (future) review page. `Cache::forget('deals.feed')` not needed (flush happens per promotion).

## Rollback

No schema changes. Revert the commits; already-promoted products are ordinary catalog rows and keep working (their `source='market'` snapshots are inert data).

## Build Log

(append one line per phase)

- **8.1 + 8.2 (2026-07-22, commit `3a16fb0`)** — both phases shipped in one commit. `App\Services\MarketPromotionService::promote()` wraps the whole promotion in a `DB::transaction`, creates the catalog row via `Product::withoutEvents()`, copies `market_price_snapshots` → `product_price_snapshots` with `source='market'` at the original observation dates (single `current_price` snapshot as the fallback when the market row has no history), sets `price_checked_at = last_seen_at` explicitly, and calls `PriceIntel::flush()` by hand because the observer path is deliberately skipped. Admin surface: `POST /admin/market-products/{market_product}/promote`, the Edit-page promote panel (category `<select>` + explainer, replaced by a green "In catalog" card once promoted) and "In catalog" chips on the Index. 9 tests in `tests/Feature/Admin/MarketPromotionTest.php`.
- **Review (gd-code-reviewer, 2026-07-22)** — findings recorded in agent memory `project_market-promote-review.md`. The `withoutEvents` + seeded-history pattern is confirmed-safe (the only Product model event is `ProductObserver`, and both of its hooks are intentionally replaced by explicit writes); the `price_checked_at == last_seen_at` assertion doubles as proof the `saving` hook was suppressed. **Standing WARN, still open:** the §Design-decisions "column-width mapping" branches — `name` truncated to 255, an over-255 `image_url` **dropped rather than truncated** (a cut URL 404s), `review_count` capped to `unsignedMediumInteger` — have no test coverage. If anything touches that mapping, add a `gd-test-engineer` case on the image_url-drop branch first.
- **Log hygiene note (2026-07-27):** this Build Log was reconstructed after the fact from the commit, the source, and the reviewer's agent memory — the phases shipped without one, and the Phase Log carried a stale "commit pending" marker for five days.
