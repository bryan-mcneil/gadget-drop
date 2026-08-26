---
name: plan11-price-compare-review
description: Plan 11 live-price-compare review (2026-08-25) — confirmed-safe patterns not to re-flag, plus the standing findings from the first pass
metadata:
  type: project
---

First `gd-code-reviewer` pass over plan 11 commits `1fdc73d..06d1128` (verdict CHANGES REQUIRED). 62 new tests pass locally.

**Confirmed safe — do not re-flag:**
- **`RequestOptions::with(timeout:, maxRetries: 0)` on `create()`, not the `Client`.** Verified against the vendored SDK: `BaseClient` line 139 does `RequestOptions::parse($this->options, $opts)` (per-call last, wins) and `timeout`/`maxRetries` are `#[Property]` (required) so `toProperties()` always emits the 600s/2-retry class defaults from the per-call object. The build log's claim is accurate. `transporter` is `#[Optional]` so it alone still binds at `Client` level and is the test seam.
- **§6(y) persistence:** grep of the three new files returns zero `save/update/create/insert/DB::/Mail::/Notification::`. Cache + `Product::find()` + `Log::` only. The 24h payload lands in the `cache` DB table (default store is `database`), swept by the existing `cache:prune-expired` 05:00 task — plan-accepted, not a finding.
- **Raw payload cached, `normalize()` on read; key fingerprints `products.price` only.** Deliberate (build log 11.3) so an "Unchanged" click in `/admin/prices` restores the winner line without buying a search.
- **`retailers_checked` clamped to whitelist size; out-of-stock rows render but never win; missing `confidence` fails closed.** All three are documented build-log decisions with tests.
- **`Cache::lock` on the database store works** — `cache_locks` exists in `0001_01_01_000001_create_cache_table.php`.
- **Em dash in `LivePriceCompare.php`'s docblock is fine** (PHP docblocks are exempt); no user-facing copy carries one.

**Standing findings (unfixed as of this pass):**
1. `matchHost()` backslash bypass — see [[host-allowlist-parse-url]].
2. `$phase`/`$result` are unlocked public props — see [[livewire-unlocked-public-props]].
3. `winner()`'s amazon-wins branch says "is the lowest" while the retailer branch says "has the lowest **in-stock** price" — asymmetric in the self-flattering direction the plan names as the load-bearing error.
4. `@livewire('live-price-compare')` in `show.blade.php` is ungated: it mounts (and runs `Product::find()`, unmemoized, twice per widget) on every review page even with `PRICE_COMPARE_ENABLED=false`, and on `tech_tip`/`tech_news` posts where every neighbouring price surface is suppressed.
5. `phpunit.xml` pins `SOCIAL_ENABLED`/GSC/Bing/IndexNow but not `PRICE_COMPARE_ENABLED` or `ANTHROPIC_API_KEY`.
6. No `TrustProxies` anywhere in the repo, so `request()->ip()` behind the Hostinger CDN may be one edge IP — the "5/hour per IP" limiter could be global. Pre-existing (`ExplainVerdict`, `JoinTheDrop` share it), verify at deploy.
