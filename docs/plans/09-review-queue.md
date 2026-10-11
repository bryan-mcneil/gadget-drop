# Plan 09 — Mention-Driven Review Selection (review queue + market-trending fallback)

**Goal:** `/drop-research` stops picking tomorrow's review purely from the open web. It picks in priority order: **(1)** a product we've already *mentioned* in a post but never reviewed (so every product we name eventually earns its own linkable review), **(2)** the top-**trending** market product (biggest recent price drop), **(3)** today's editorial / SEO-brief pick as before. The queue is a real table, fed at import time by a structured `MENTIONS` field and backfilled once from existing post bodies; the overnight cloud agent — which has **no database access** — reaches both new tiers through one key-protected API endpoint, exactly as it already reaches the reviewed-products dedupe. `/morning` tells Bryan when a pick fulfilled a mention, with a link to the source post.
**Size:** M · **Branch:** main (no feature branches — 2026-07-18 convention) · **Depends on:** market layer (on main), the `/api/reviewed-products` + `api.key` pattern.
**Non-goals:** NLP extraction inside a command (backfill = an LLM/agent extraction pass that writes a seed file → a *deterministic, tested* importer loads it — same split as `market:import` and `bin/daily-drop-build.php`, where the model never writes the final rows); auto-writing or auto-publishing (the queue only *orders candidates* — the editorial value gate and every honesty rule in `/drop-write` are unchanged); a public "upcoming reviews" surface (thin, and bad for the AdSense posture); any change to cadence (still 11/week), `market:import`, or the promotion flow (Plan 08).

Context for the implementer: today's Arzopa G1 post names the *ASUS ZenScreen MB16AHV* in a prose sentence — that mention is **free text**, captured nowhere. `post_products` holds only the one CTA product; research's `ALTERNATIVES` field never reaches `output.json` or the DB. The cloud agent (`daily-drop/CLOUD-AGENT.md`) "has no database access — you only produce files"; its live signals are repo files + web + `GET /api/reviewed-products` (behind `api.key`, key `GADGETDROP_API_KEY`). So both new tiers must arrive over an API, and `market_products` has **no trending column** — trending is computed from `market_price_snapshots` (biggest recent price drop, Bryan's call). `posts.type` and any status columns are strings, not DB enums — keep the queue's `status` a `string(16)` to avoid the sqlite CHECK trap (CLAUDE.md Tests).

## Phase Log

- [ ] Phase 9.1 — `review_queue` table + model + import-time capture & resolution
- [ ] Phase 9.2 — Backfill: `review-queue:import` command + one-time seed from existing posts
- [ ] Phase 9.3 — `MarketTrending` support class (biggest recent price drop, gated + cached)
- [ ] Phase 9.4 — `/api/review-candidates` endpoint (tier-1 queue + tier-2 trending)
- [ ] Phase 9.5 — Pipeline wiring: `/drop-research`, `/drop-write`, build script, `/morning`, cloud-agent doc

## Design decisions

- **The queue is a candidate *ordering*, never a lowered bar.** Tiers 1 and 2 only re-rank *what to consider first*. A queued product still gets written only if `/drop-research`'s value gate passes (we can add real value: tracked price history, spec/owner-feedback analysis, a true comparison) and `/drop-write`'s honesty rules hold. Demand/mention alone is never a reason to write.
- **One row per distinct candidate; dedupe by ASIN then normalized name.** Mentions usually arrive without an ASIN, so the dedupe key is `asin` when present, else `name_key` (lowercased, punctuation/whitespace-collapsed). Re-mentioning a product increments `mention_count` (the tier-1 ranking signal) instead of inserting a duplicate; when an ASIN later appears for a name-only row, it fills in.
- **Resolve at import, not at publish.** `posts:import` creates a *draft* review; the moment that draft exists we are committed to the product, so matching pending rows flip to `status='reviewed'` (with `resolved_post_id`) and stop being suggested. Keying resolution off the imported post's own product ASIN/name means it works before Bryan publishes. (If a draft is later deleted the row stays `reviewed` — acceptable; re-import re-resolves.)
- **Never enqueue what's already covered.** At capture time skip any mention whose ASIN matches a curated `products` row or an existing review, and skip the post's own CTA product. `MENTIONS` carries only the cited *alternatives*, so the CTA product is naturally excluded — the importer belt-and-suspenders it anyway.
- **Trending = biggest recent price drop, gated like PriceIntel.** `MarketTrending` ranks `market_products` by `(recent_high − current_price) / recent_high` over a carry-forward series from `market_price_snapshots`; honesty gate mirrors PriceIntel (needs ≥2 snapshots; a flat price is not "trending"); only positive drops rank; already-reviewed ASINs are excluded (we want *unreviewed* market products). db-cached 6h with an explicit `flush()`, defensive like `PriceIntel`/`DropPrice`.
- **The agent stays DB-free.** Both tiers ship over `GET /api/review-candidates` behind the existing `api.key` middleware — no new secret (reuse `GADGETDROP_API_KEY`), same contract shape as `/api/reviewed-products`.
- **Extraction is an LLM pass → a deterministic importer.** Backfill mentions are extracted from post bodies by a model/agent into a seed file; `review-queue:import {file}` validates and upserts it. The command is the tested unit; the extraction is documented, not encoded (mirrors the pipeline's "model never writes the final JSON" rule).
- **`status` is `string(16)`, not a DB enum** (`pending|picked|reviewed|dismissed`) — enums trip the sqlite test schema (CLAUDE.md).

---

## Phase 9.1 — `review_queue` table + model + import-time capture & resolution

**Scope:** the migration, the `ReviewQueue` model, a `MENTIONS` field flowing `product-N.md → output.json`, and `posts:import` enqueue+resolve logic. No API, no backfill, no trending yet.

Steps:
1. Migration `create_review_queue_table`: `id`; `name string`; `name_key string index`; `asin char(10) nullable index`; `brand string nullable`; `category string nullable`; `source string(16)` default `'mention'` (`mention|market|manual`); `source_post_id` FK→posts nullable (`nullOnDelete`); `context string(500) nullable`; `status string(16)` default `'pending'`; `mention_count unsignedInteger` default 1; `picked_at datetime nullable`; `resolved_post_id` FK→posts nullable (`nullOnDelete`); timestamps. Index `(status, mention_count)` for the tier-1 ranking read. No unique constraint (nullable-ASIN duplicates + name-key merges are enforced in code, not the schema).
2. `app/Models/ReviewQueue.php` — `$fillable`, `datetime` casts, `scopePending`, a `belongsTo` `sourcePost` / `resolvedPost`, and a static `normalizeKey(string $name): string` used everywhere a `name_key` is written or matched.
3. `App\Support\ReviewQueueWriter` (or a method on the model) — `enqueue(array $mention, ?Post $sourcePost)`: skip if ASIN matches a curated `Product` or the mention is already `reviewed`; upsert by `asin` else `name_key` (increment `mention_count`, back-fill a missing ASIN); returns the row or null. `resolveFor(Post $post)`: mark pending rows `reviewed` (+`resolved_post_id`) where `asin` == the post's product ASIN **or** `name_key` == the post's product name key.
4. `bin/daily-drop-build.php` — parse a new optional `MENTIONS:` block in `product-N.md` (lines `name | asin? | category?`) into `output.json` as `"mentions": [{name, asin?, category?}]` per post. Validate ASIN shape when present; **warn** (not error) if the body has a "vs./compares" section but `MENTIONS` is empty.
5. `posts:import` — for each imported article: after saving the post + its CTA product, `resolveFor($post)`, then `enqueue()` each `mentions[]` entry with `source_post_id = $post->id`. Dry-run performs no writes but reports the counts.

Tests:
- `tests/Unit/ReviewQueueTest.php` — `normalizeKey` collapses case/punct/space; `enqueue` upserts (dupe by asin → `mention_count` bumps, no new row), name-only row gains an ASIN on a later asin'd enqueue, skips a mention already in `products`; `resolveFor` flips matching pending rows to `reviewed` by asin and by name-only.
- `tests/Feature/DailyDropBuildScriptTest.php` (extend) — a `product-N.md` with `MENTIONS` yields `mentions[]` in `output.json`; malformed ASIN warns; empty-mentions-with-compare-section warns; exit code unchanged.
- `tests/Feature/ImportDropPostsCommandTest.php` (extend) — importing an article enqueues its mentions as `pending`, skips a mention that already has a review, resolves a pre-seeded pending row matching the imported product; `--dry-run` writes nothing.

Commit: `feat(queue): review_queue table + import-time mention capture and resolution`

Review checklist: `status`/`source` are `string`, not enum; resolution matches on asin OR name_key; enqueue never inserts the post's own CTA product; dry-run is write-free; FK deletes are `nullOnDelete` (a deleted post must not cascade-drop queue history).

## Phase 9.2 — Backfill: `review-queue:import` command + one-time seed

**Scope:** a deterministic importer command and the one-time seed file that puts existing mentions (incl. the ASUS ZenScreen) into the queue.

Steps:
1. `app/Console/Commands/ReviewQueueImport.php` — `review-queue:import {file}`: reads a JSON array `[{name, asin?, brand?, category?, source_post_slug?, context?}]`, resolves `source_post_slug`→`source_post_id` when given, calls the Phase 9.1 `enqueue()` per row (so dedupe/skip-reviewed/`source='manual'` or `'mention'` all reuse one path), writes a JSON run report to `storage/app/review-queue/`. Idempotent: a second run inserts nothing new. `--dry-run` supported.
2. Generate the seed: an extraction pass (documented in the command's help + `docs/` note) over existing post bodies produces `daily-drop/review-queue-seed.json`; commit the seed file alongside the command so the backfill is reproducible. Include the ASUS ZenScreen MB16AHV (source: the Arzopa G1 post) and any other named-but-unreviewed products found.
3. Run once on prod after deploy (Deployment section) — not in the migration.

Tests:
- `tests/Feature/ReviewQueueImportCommandTest.php` — imports a seed fixture (rows created, `source_post_slug` resolved to id, unknown slug → null source), dedupes on a second run (idempotent), skips a row whose ASIN already has a review, `--dry-run` writes nothing, malformed file → non-zero exit with a clear message.

Commit: `feat(queue): review-queue:import backfill command + existing-mention seed`

Review checklist: command reuses the Phase 9.1 enqueue path (no parallel insert logic); idempotent; report written under `storage/app/review-queue/`; seed committed and human-readable.

## Phase 9.3 — `MarketTrending` support class

**Scope:** the tier-2 signal — biggest recent price drop across `market_products`, gated and cached. No API yet.

Steps:
1. `app/Support/MarketTrending.php` — `top(int $limit = 10): array`. Per market product with ≥2 snapshots: build a carry-forward daily series from `market_price_snapshots` over a 90-day window, take `recent_high`, compute `drop_pct = (recent_high − current_price) / recent_high`; keep only positive drops; exclude ASINs that already exist in curated `products` (unreviewed candidates only) and any lacking a usable ASIN. Sort by `drop_pct` desc, return `[{asin, title, brand, category, current_price, recent_high, drop_pct, url}]`. Honesty gate + defensive fallbacks mirror `PriceIntel`; guard `Cache::remember` so it survives a non-Laravel unit boot (same pattern as `ArticleBody`). db cache, 6h TTL, `flush()` busts the key.
2. Register no schedule/observer — it reads on demand and caches; imports already write snapshots.

Tests:
- `tests/Unit/MarketTrendingTest.php` — a product with a genuine drop over ≥2 snapshots ranks with the right `drop_pct`; a flat-price product is excluded (not "trending"); a single-snapshot product is excluded (gate); an ASIN already in `products` is excluded; ordering is by drop desc; `flush()` clears the cache.

Commit: `feat(queue): MarketTrending — biggest-recent-drop ranking, gated and cached`

Review checklist: gate is ≥2 snapshots (a flat series never surfaces); already-reviewed ASINs excluded; cache key flushable; class works without a full Laravel boot.

## Phase 9.4 — `/api/review-candidates` endpoint

**Scope:** one key-protected endpoint the cloud agent calls before the open web. No pipeline edits yet (this phase is independently testable).

Steps:
1. `app/Http/Controllers/Api/ReviewCandidatesController.php` — `index()` returns `{"queue": [...], "trending": [...]}`: `queue` = pending `ReviewQueue` rows ordered by `mention_count` desc then `created_at` asc, limited (10), each `{name, asin, brand, category, source_post_slug, context, mention_count}` (resolve `source_post_id`→slug for the link-back); `trending` = `MarketTrending::top(10)`.
2. Route in `routes/api.php` inside the existing `api.key` group: `Route::get('/review-candidates', [ReviewCandidatesController::class, 'index']);`.

Tests:
- `tests/Feature/Api/ReviewCandidatesTest.php` — 401/403 without the key; with the key returns the two-array shape; `queue` excludes `reviewed`/`dismissed` rows and orders by `mention_count`; `source_post_slug` present when the row has a source post; `trending` reflects `MarketTrending` (assert a seeded drop appears, a flat one doesn't); both arrays honor the limit.

Commit: `feat(queue): /api/review-candidates — tier-1 queue + tier-2 trending for the agent`

Review checklist: behind `api.key` like reviewed-products; no N+1 on source-post slugs (eager-load); limits enforced server-side; response shape documented in the controller.

## Phase 9.5 — Pipeline wiring (skills + build script + morning + cloud-agent doc)

**Scope:** teach the pipeline to *use* the new tiers and to *report* the source. Mostly skill/doc edits; the only code is the build script passing the source through and `/morning` printing it.

Steps:
1. `.claude/commands/drop-research.md` — new **Step 0.4 "Consult the review queue + trending"** before the web search: call `GET /api/review-candidates` (same auth pattern the file already uses for reviewed-products). Tier 1: take the top `queue` item that passes the existing value gate; resolve its ASIN via web if null. Tier 2: else the top `trending` item that passes the gate. Tier 3: else editorial/SEO-brief as today. Record new `research.md` fields `SOURCE: review-queue|market-trending|editorial` and (tier 1) `SOURCE_POST: /posts/{slug}`. Update the file-format block + the value-gate wording (queue/trending re-rank, they do not lower the bar).
2. `.claude/commands/drop-write.md` — emit a `MENTIONS:` block (the cited `no-review` alternatives: `name | asin? | category?`) and pass `SOURCE`/`SOURCE_POST` from `research.md` through to `product-N.md`. Note the internal-link rule already covers linking a queued product's source post back.
3. `bin/daily-drop-build.php` — carry `SOURCE`/`SOURCE_POST` into `output.json` per post (`selection_source`, `fulfills_mention_post`) alongside the Phase 9.1 `mentions`. (`posts:import` needs no further change — it already enqueues/resolves.)
4. `.claude/commands/morning.md` — Step 7 checklist: when a draft's `selection_source == 'review-queue'`, print a `↳ fulfills a mention in "{source post title}" ({/posts/slug})` line under it; when `'market-trending'`, print `↳ trending market pick (recent price drop)`. Pull these from `output.json` (already parsed via the build script).
5. `daily-drop/CLOUD-AGENT.md` — document the tier order + the `/api/review-candidates` call (reuses `GADGETDROP_API_KEY`; degrades gracefully if the API is unavailable — fall back to editorial and say so in `research.md`).
6. CLAUDE.md — one bullet under the pipeline description pointing at this plan + the tier order. Plans README — add the row.

Tests:
- `tests/Feature/DailyDropBuildScriptTest.php` (extend) — `SOURCE`/`SOURCE_POST` in `product-N.md` surface as `selection_source`/`fulfills_mention_post` in `output.json`; absence defaults to `editorial`/null. (Skill/doc prose is not unit-tested; the build-script pass-through is the tested seam.)

Commit: `feat(queue): wire tiered selection into research/write/build/morning + docs`

Review checklist: `/drop-research` keeps the value gate as the final authority; API failure degrades to editorial (agent never hard-fails); `/morning` report reads from `output.json`, not the DB; no new secret introduced.

## Deployment

Shared template (plans README) — deltas:
- Migration adds `review_queue` (`migrate --force` creates it).
- **One-time backfill after deploy:** `php artisan review-queue:import daily-drop/review-queue-seed.json` on the server (seeds the ASUS ZenScreen + existing mentions). Verify with `php artisan tinter`/a count and by hitting `GET /api/review-candidates` with the key.
- Confirm `route:cache` didn't shadow the new `/api/review-candidates` route (it's registered in `routes/api.php` under the same group as reviewed-products).
- No public HTML/asset change → no CDN purge needed; no `deals.feed` flush needed.
- First cloud-agent run after deploy should show `SOURCE: review-queue` in its `research.md` if the queue is non-empty — that's the end-to-end confirmation.

## Rollback

Drop the `review_queue` table (`down()` provided) and revert the commits. The API route, `MarketTrending`, and the skill/doc edits are inert without callers; `output.json`'s extra fields are ignored by an older importer. Already-enqueued rows are pure metadata — nothing public depends on them.
