---
name: dusk-priceintel-seeding
description: Dusk verdict/price seeding uses real relative dates (no setTestNow) + trailing PriceIntel::flush() because CACHE_STORE=file survives DatabaseTruncation — confirmed-safe, don't re-flag
metadata:
  type: project
---

`tests/Browser/VerdictSurfacesTest.php` (plan 01 §1.5) seeds PriceIntel fixtures the same way `DealsPageTest`/`Feature\VerdictSurfacesTest` do: `Product::create` at the historic price, a manual `ProductPriceSnapshot` at `now()->subDays(60)`, then `$product->update(['price' => $current])` (the ProductObserver appends the today snapshot), and the seed helper ALWAYS ends with `PriceIntel::flush($id)`.

**Why:**
1. `.env.dusk.local` sets `CACHE_STORE=file`, shared by the `php artisan serve` and the `php artisan dusk` processes. `DatabaseTruncation` clears DB tables between tests but does NOT clear the file cache, and truncation resets sqlite autoincrement so product IDs repeat — a stale `priceintel.{id}`/`deals.feed` from a prior test would poison the next. The trailing `flush()` (busts both keys, cross-process) is load-bearing, not defensive noise.
2. The plan text said "freeze time helpers in the seeder", but the implementation uses real relative dates (`subDays(60)`) with NO `Carbon::setTestNow` — a conscious lean reading, disclosed in the commit body + Build Log. It's safe: the 60-day span clears the ≥14-day gate with ≥46 days of slack, and PriceIntel is day-granular (`startOfDay`), so even a midnight-boundary clock skew between the two processes shifts spanDays by ≤1 and cannot flip a verdict here. The feature-suite fixtures use the identical relative-date approach and are green — they are the math oracle.

**How to apply:** Don't re-flag the missing time-freeze or "why flush again" in Dusk price fixtures — both are correct. DO verify any NEW Dusk price fixture still ends with `PriceIntel::flush()` after its LAST snapshot write (a flush before a later write leaves a stale cache the serve process will read). Fixture-math spot check: verdict `lowest` needs today == 90-day carry-forward low WITH variation; a plain drop like 100→80 reads `lowest` (today IS the record low) — use the dip-and-recover shape (100→70→80) when you need `good` with a distinct low30. Related: [[verdict-badge-card-width]], [[honesty-gate-tri-surface]], [[dusk-sqlite-truncation]].
