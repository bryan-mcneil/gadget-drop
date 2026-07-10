# Plan 03 — Post-Purchase Price Watch

**Goal:** "Already bought it? We'll watch the price for your return window." A reader enters their email + purchase date on any review; if the tracked price drops meaningfully within Amazon's 30-day return window, they get one email: *"It dropped $23 — return & rebuy saves you money."* Nobody in the affiliate-content space owns this moment; Amazon itself recommends return-and-rebuy since it ended price protection.
**Size:** M · **Branch:** `feature/post-purchase-watch` · **Depends on:** Plan 00; Plan 01 (verdict language in emails)
**Non-goals:** general-purpose watchlists (future plan — this table is designed to grow into it); tracking products outside the catalog (would require scraping — never); automating anything against Amazon.

## Phase Log

- [ ] Phase 3.1 — Schema + model + pruning (commit: )
- [ ] Phase 3.2 — Signup Livewire component + verification mail (commit: )
- [ ] Phase 3.3 — `watches:check` hourly command + drop alert mail (commit: )
- [ ] Phase 3.4 — Closing-window courtesy mail (optional but recommended) (commit: )
- [ ] Phase 3.5 — Dusk pass + privacy-page copy (commit: )

## Design decisions

- **Catalog products only, existing snapshot sources only.** The watch reads `products.price` as refreshed by `prices:refresh` (PA-API → Canopy budget-guarded → manual `/admin/prices`). Zero new API spend; structurally $0 holds. This means coverage = "products we track," which is exactly the honest promise the UI copy makes.
- **Magic links, no accounts:** a UUID `token` per watch (the `subscribers.token` precedent) + Laravel **signed URLs** for verify/unsubscribe links (belt and suspenders: signature proves the link came from us, token identifies the watch).
- **Trigger threshold:** drop ≥ `max($5, 3%)` below `purchase_price`. Constants in `config/watch.php` — tunable without code.
- **One alert per watch** (`notified_at`). Re-notification tiers are a future decision, not v1 complexity.
- **Hourly cadence is enough:** the hPanel cron fires `schedule:run` hourly at :00, and `->hourly()` runs at minute 0 (see `social:publish` precedent) — a return window is 30 days; hour-granularity is generous.
- **Email volume stays trivial** (watches are per-reader-per-product); the existing mailer that sends the Friday newsletter handles it. No queue infrastructure.
- **Privacy:** email + purchase date is light PII. Auto-prune rows 60 days after expiry; unsubscribe in every mail; privacy page updated (Phase 3.5).

---

## Phase 3.1 — Schema + model + pruning

**Scope:** migration, `PriceWatch` model, factory, relationships, prune logic.

Steps:
1. Migration `create_price_watches_table`: `id`; `product_id` FK constrained cascadeOnDelete; `email` string indexed; `purchase_price` decimal(10,2); `purchased_at` date; `expires_at` date (computed at insert: `purchased_at + 30 days`); `token` uuid unique; `verified_at` nullable timestamp; `notified_at` nullable timestamp; `closing_mail_sent_at` nullable timestamp (Phase 3.4 uses it; cheaper to add now than migrate later); `ip_address` string nullable (abuse forensics, pruned with the row); timestamps. Indexes: `(expires_at)`, `(product_id, verified_at)`.
2. Model: fillable, casts (`purchased_at`/`expires_at` date, the three stamps datetime); scopes `active()` (verified, not expired, not notified), `expired()`; helper `savings(float $current): float`.
3. `Product::priceWatches()` hasMany.
4. Prune: `PriceWatch::prunable()` (Laravel `Prunable` trait) — `expires_at < now()->subDays(60)`. Wire `model:prune` into `routes/console.php` at `dailyAt('05:00')` alongside `cache:prune-expired` (minute :00 rule ✓).

Tests (`tests/Feature/PriceWatchTest.php`): expiry computed on create; scopes; prunable window (freeze time, assert pruned/kept); cascade with product deletion.

Commit: `feat(watch): price_watches schema, model, 60-day prune`

Review checklist: date-vs-datetime casts deliberate; no MySQL-only syntax; prune registered at :00; `ip_address` nullable and never rendered anywhere.

## Phase 3.2 — Signup Livewire component + verification mail

**Scope:** `app/Livewire/PriceWatchSignup.php` + view; verification Mailable; verify route; placement under the product card.

Steps:
1. Component (class-based): props `Product $product`; fields `email`, `purchased_on` (date, default today, max today, min today−30d — an already-closed window is pointless); honeypot field + min-form-age timestamp (mirror `ContactForm`'s anti-bot pattern — read that component first and copy its exact approach); RateLimiter `watch-signup:{ip}` 5/hour. On submit: reject duplicates (same email+product, active) with a friendly "already watching" state; create watch (unverified) + send `WatchVerifyMail` (signed URL `watch.verify` embedding token, 48h signature expiry).
2. Routes (`routes/web.php`, public block): `GET /watch/verify/{token}` → sets `verified_at` (abort 403 on invalid signature, 404 unknown token, friendly "already verified" state) → renders a tiny confirmation Blade view (serverMeta: noindex via existing seo pattern); `GET /watch/unsubscribe/{token}` → deletes the watch, confirmation view. Route names: `watch.verify`, `watch.unsubscribe`.
3. `WatchVerifyMail` (markdown mailable, matches newsletter styling): what we'll do, the threshold promise, expiry date, unsubscribe link.
4. Placement: `<livewire:price-watch-signup :product="$product" />` directly below `<x-price-history>` on review pages — copy: "Already bought it? We'll watch this price until {window closes} and email you if it drops enough to return & rebuy." **No new affiliate link** (single-CTA holds; this widget's CTA is an email field).
5. UI states: form → check-your-email → verified (if they land back). `x-cloak` where Alpine-toggled; static initial classes.

Tests (`tests/Feature/Livewire/PriceWatchSignupTest.php` + `PriceWatchFlowTest`): validation (email, date bounds); honeypot silently drops; throttle; duplicate-active friendly path; mail queued/sent with signed URL (Mail::fake + assert signature validates); verify route happy/expired-signature/bad-token paths; unsubscribe deletes.

Commit: `feat(watch): signup component, verification + unsubscribe flow`

Review checklist: honeypot matches ContactForm's proven pattern; signed URLs (not just token); date bounds enforced server-side; noindex on the utility pages; copy promises only what the tracker can deliver ("prices we track", not "Amazon prices live").

## Phase 3.3 — `watches:check` hourly command + drop alert

**Scope:** the money moment — `app/Console/Commands/CheckPriceWatches.php` + `WatchDropMail`.

Steps:
1. Command `watches:check`: iterate `PriceWatch::active()` **whose product's `price` is non-null**, chunked. Trigger when `purchase_price − current ≥ max(config('watch.min_drop_abs'), purchase_price × config('watch.min_drop_pct'))`. On trigger: send `WatchDropMail`, stamp `notified_at`. Idempotent by design (stamp checked in the `active()` scope); safe to run any number of times.
2. `config/watch.php`: `min_drop_abs` (5.00), `min_drop_pct` (0.03), plus `window_days` (30) used by 3.1/3.2 — single source.
3. `WatchDropMail`: current price vs paid; savings; days left in the window; **exact steps** ("Amazon no longer price-matches — return the original and rebuy at the lower price; check the item is marked Free Returns first"); link to the review page (`wire:navigate` irrelevant in mail; plain URL) — affiliate link policy: link the REVIEW page, not `/out` (email → out would skew click attribution and Associates policy prefers disclosure context; the review page holds the CTA).
4. Schedule: `Schedule::command('watches:check')->hourly();` in `routes/console.php` with the standard minute-:00 comment.
5. Observability: `info()` log line per run: scanned/triggered counts (greppable by `/gd-health`).

Tests (`tests/Feature/CheckWatchesCommandTest.php`, Mail::fake + Carbon::setTestNow): drop past threshold → one mail + stamp; below threshold → nothing; absolute-vs-percent branch both directions; expired/unverified/already-notified all skipped; product price null skipped; second run sends nothing (idempotence); mail content includes savings + days-left.

Commit: `feat(watch): hourly watches:check with return-window drop alerts`

Review checklist: `->hourly()` (== minute :00) ✓; chunked query (no full-table load); no new price fetching anywhere (reads `products.price` only); mail copy factual about Amazon policy (no guarantees).

## Phase 3.4 — Closing-window courtesy mail (recommended)

**Scope:** the honesty flourish: ~3 days before expiry, if never notified, send one summary: *"Your return window closes {date}. The price never dropped below what you paid — per our 90-day data you paid a {verdict} price."* Turns a non-event into brand trust + a natural "keep watching deals" newsletter invite (existing Join the Drop link, not auto-subscribe).

Steps: extend `watches:check` (same run): `active()` watches with `expires_at` within `config('watch.closing_notice_days', 3)` days and `closing_mail_sent_at` null → `WatchClosingMail` (pull verdict language from `PriceIntel::stats()`), stamp. Tests: window edges, verdict text present, idempotence, never sent after a drop alert.

Commit: `feat(watch): closing-window summary mail with verdict context`

Review checklist: one mail max (stamp) ✓; no upsell pressure in copy (invite, not push); PriceIntel null-stats path handled (young products → "we don't have enough history to judge — here's what we saw").

## Phase 3.5 — Dusk pass + privacy copy

Steps:
1. Dusk `tests/Browser/PriceWatchTest.php`: signup happy path on a seeded review (Mail faked at the app layer — assert the check-your-email state), duplicate state, and the verify route rendered directly.
2. `resources/views/public/static` privacy page: add the watch data-handling paragraph (what we store, why, 60-day post-expiry deletion, unsubscribe path). Feature test asserts the section renders.

Commit: `test(watch): Dusk flow + privacy disclosure`

## Testing summary

Feature: ~20 new tests across schema/flow/command/mails. E2E: 2–3 Dusk. Time-sensitive logic ALL under `Carbon::setTestNow`. Suite target: +~22 green.

## Deployment

Standard template + deltas: `migrate --force` (additive table; down = drop, no data risk pre-launch); confirm Hostinger mail limits comfortably exceed expected volume (newsletter already sends more at once than watches will trigger per hour); verify `schedule:run` picked up both new entries (`php artisan schedule:list` on server — everything at :00); post-deploy smoke: create a real watch on prod with your own email, verify the mail arrives, then unsubscribe.

## Maintenance

- `/gd-health` watches: signups/week, verify-rate (a low rate = mail deliverability problem), triggered-alert count, prune executions, table size.
- Deliverability: if verify mails land in spam, revisit SPF/DKIM on the domain (one-time Hostinger DNS check — do it at launch).
- Future evolutions logged here when data justifies: re-notify tiers, general watchlists (drop `expires_at` semantics), weekly digest merge (Plan idea 12 from the Feature Lab).

## Risks

- **Price staleness** (Canopy 3/day budget): a drop we see late shrinks the reader's action window. Mitigation: copy says "as of our last check {checked_at}"; `/admin/prices` manual pass stays in the daily routine for watched products (`/gd-health` lists products with active watches + stale `price_checked_at`).
- **Emotional misfire** (alert arrives after window closed due to staleness): the `active()` scope excludes expired watches — an alert can never postdate the window it cites.

## Build Log

(append one line per phase)
