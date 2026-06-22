# Drop Price — Homepage Daily Game · Implementation Plan

> **Status:** Approved plan. Built in 9 reviewable phases — each phase is reviewed before the next begins.
> v1 = Phases 1–6, 8, 9 + the CLI slice of 7. Later = cross-device sync, full admin UI, per-TZ midnight.

---

## Context

GadgetDrop is an Amazon-affiliate site whose business depends on **organic traffic → return visits → affiliate clicks**. Right now there's no reason for a visitor to come back tomorrow. **Drop Price** is a Wordle-style daily game on the homepage: guess a mystery gadget's Amazon price in 4 tries. It exists to:

1. **Build a daily habit** (a fresh puzzle every day = a reason to return),
2. **Capture emails** by letting players *save their streak* (which also subscribes them to the existing newsletter), and
3. **Drive affiliate clicks** — when the price is revealed ("only $X — cheaper than you guessed!"), the Amazon CTA is right there.

It shares the homepage hero band with the existing carousel: **60% carousel / 40% game** on desktop, stacked (carousel first) on mobile.

### Locked design decisions
| Decision | Choice |
|---|---|
| Win condition | **Exact whole-dollar** guess wins. No tolerance band. |
| Feedback per guess | Direction (higher/lower) **+ 3-band closeness meter**: 🥶 freezing (>25% off), 😊 warm (10–25%), 🔥 hot (<10%, not exact), 🎯 nailed it (exact = win). **Ordinal only** — never show the exact dollar distance mid-game. |
| Cadence | **One global puzzle/day** for everyone; resets at **midnight UTC**; price **snapshotted** at lock time. |
| Product source | **Auto-pick + admin override.** Scheduled command picks an eligible product; admin can pre-set a future day. |
| Product visibility | **Shown openly** (image + name) while guessing. |
| Email gating | **Free play for all.** Streak in localStorage. Prompt to save (= subscribe) after a win or once play-streak ≥ 2. |
| Streak meaning | Headline = **play streak** (consecutive days played, survives losses). Secondary bragging stats: total wins, win rate, current/best win streak, closest miss. |
| Sharing | **Spoiler-free shareable result in v1** (emoji rows + day number + URL, copied to clipboard). |
| Layout | Desktop 60/40 (carousel left / game right); mobile carousel-first, game below. |

### The one hard technical constraint
**The day's price is the secret answer and must never reach the browser before the reveal.** All guess validation happens **server-side** (Livewire). The product image/name may be shown; the price (and anything that narrows it) is rendered only after the game ends.

---

## Phase 1 — Data model & migrations

**Goal:** Persist the daily puzzle and per-subscriber streak/results.

**Storage strategy (hybrid, deliberate):** the **play streak** is owned by the browser (`localStorage`) because anonymous players have no server row until they save an email; the server keeps **denormalized counter columns** on `subscribers` as the backup/sync snapshot, plus a **per-play results table** as the source of truth for win stats. This cleanly survives the anonymous → saved transition without backfilling streaks from server rows.

**New files:**
- `database/migrations/2026_06_19_000001_create_drop_price_puzzles_table.php`
- `database/migrations/2026_06_19_000002_create_drop_price_results_table.php`
- `database/migrations/2026_06_19_000003_add_drop_price_streak_to_subscribers_table.php`
- `app/Models/DropPricePuzzle.php`, `app/Models/DropPriceResult.php`

**`drop_price_puzzles`** — `puzzle_number` (unsigned, unique, sequential → "Drop Price #142"), `date` (date, **unique**), `product_id` (FK → products, **nullOnDelete**), **`price` (unsignedInteger — the snapshotted whole-dollar answer)**, `product_name` + `product_image_url` (snapshots so old puzzles survive product edits/deletes), `affiliate_product_id` (nullable, for the reveal CTA), `locked_at`, `is_preset` (bool, admin override flag), timestamps.

**`drop_price_results`** — `drop_price_puzzle_id` (FK), `subscriber_id` (FK), `won` (bool), `guesses_used` (tinyint), `closest_miss_pct` (smallint nullable), `played_on` (date, denormalized), timestamps. **Unique `(subscriber_id, drop_price_puzzle_id)`** (one result/puzzle/subscriber; upsert on save).

**`subscribers` additions** (denormalized snapshot, updated via explicit `update()`/`increment()`, **not** mass-assignable): `play_streak`, `best_play_streak`, `win_streak`, `best_win_streak`, `total_plays`, `total_wins` (default 0), `closest_miss_pct` (nullable), `last_played_on` (date nullable).

All column types are sqlite-safe → **no `DB::getDriverName()` guard needed** (that guard is only for MySQL-only DDL).

**Verify:** `php artisan migrate` (MySQL) + `php artisan test` (sqlite) build clean; factory-create puzzle+result, assert relationships.

---

## Phase 2 — Daily selection command + schedule ✅ DONE

**Goal:** Lock the next puzzle automatically at a UTC minute :00, with eligibility, **unique-forever** product selection (sets up a replay archive), sequential numbering, and an admin override.

**New file:** `app/Console/Commands/LockDailyDropPrice.php` (signature `dropprice:lock {--product=} {--date=}`). **Modify:** `routes/console.php`.

**`handle()`:**
1. Target date = today (UTC) (or `--date`). Already-locked puzzle for it → exit idempotently (the hourly cron may fire twice).
2. **Eligible products:** `Product` with non-empty `image_url`, `price > 0`, attached via `post_products` to a **published** post (`whereHas('posts', fn($q) => $q->published())`).
3. **Unique-forever selection** *(revised from the original "recent 30" window, 2026-06-19):* exclude **every** product that has ever been a puzzle (`whereNotIn('id', <all used product_ids>)`, derived from `drop_price_puzzles` — no extra column; the puzzles table *is* the "used" flag). Every puzzle is therefore a distinct product, so the planned **replay archive is never spoiled**. The daily-drop pipeline adds ~4 products/day vs 1 consumed, so the pool rarely runs dry; if it does, fall back to the **least-recently-used** eligible product (`leastRecentlyUsedProduct()`) so the homepage never repeats yesterday.
4. **Snapshot price as whole dollars:** `(int) round((float) $product->price)` — rounds to nearest dollar (a $199.99 item's answer = 200).
5. `puzzle_number = (max ?? 0) + 1`, assigned **at lock time** (see preset note).
6. Insert puzzle row with snapshots, `locked_at = now()`.
7. `Cache::forget('dropprice.today')` so the homepage picks it up.
8. No eligible product at all → **log a warning, don't throw** (keep yesterday's puzzle; homepage must never 500).

**Admin override (data-level):** a row pre-created with `is_preset = true` for a future date is respected by the command (keeps the admin's product choice; re-snapshots price at lock). The `--product=ID --date=YYYY-MM-DD` options create such a row from the CLI — this **is** the v1 override mechanism (no UI needed). A future date stays **queued (unlocked, `puzzle_number` null)**; a today/past date locks immediately. **`puzzle_number` was made nullable** (migration 000001) so a queued preset has no number until it locks on its date — keeping numbering chronological even when presets are queued ahead.

**Schedule (`routes/console.php`), at minute :00 per the hourly-cron rule:**
```php
Schedule::command('dropprice:lock')->dailyAt('00:00');
```
Production cron is hourly, so the puzzle locks within the first UTC hour; the read layer (Phase 3) falls back to the latest puzzle in the gap.

**Verified:** `tests/Feature/DropPriceCommandTest.php` (11 tests) — eligible vs ineligible (no image / price 0 / unpublished / no post); unique-forever exclusion; LRU fallback when the pool is dry; sequential number; one row/date; integer price; idempotent re-run; preset queued-unlocked / today-instant / queued-then-locked; unknown preset product fails. Suite green at 70.

> **Future phase — replay archive:** players revisit older puzzles (e.g. `/drop-price/{number}`). The data model already supports it (each puzzle snapshots name/image/price/number; `evaluate()` is puzzle-agnostic; localStorage keyed by puzzle_number). Unique-forever selection (above) is what keeps replays un-spoiled.

---

## Phase 3 — Server-side game logic (`App\Support\DropPrice`) ✅ DONE

**Goal:** A pure, tested class that evaluates a guess **without exposing the price**, plus the cached fetch of today's puzzle.

**Built:** `app/Support/DropPrice.php` with `evaluate()` (pure ordinal scoring) + `today()`. `evaluate()` returns exactly `['direction','band','won']` — no `pct`/distance key ever leaves the method. `today()` resolves the **latest locked puzzle dated today-or-earlier** (`whereNotNull('locked_at')->whereDate('date','<=',today)->orderByDesc('date')->first()`) — a single query that returns today's once locked and otherwise the most recent prior puzzle during the pre-cron gap, while never serving an unlocked future preset or a future-dated row. Cached on the DB store via `Cache::remember('dropprice.today', now()->endOfDay(), …)` (busts at the UTC day boundary; the lock command also `Cache::forget`s it), wrapped in try/catch so a broken cache layer degrades to a live DB read. Verified by `tests/Unit/DropPriceTest.php` (6 pure tests: exact win, direction symmetry, inclusive warm boundary at 9/10/25/26%, over-vs-under symmetry, never-falsely-won, and an assertion that the result shape carries no distance key). `today()`'s resolution/fallback is covered by the Phase 8 Livewire + PublicPages feature tests (it needs a booted DB). Suite green at **76 tests**.

**New file:** `app/Support/DropPrice.php`.

**Tunable constants:** `BAND_FREEZING = 0.25`, `BAND_WARM = 0.10`, `MAX_GUESSES = 4`.

**`evaluate(int $guess, int $answer): array`** → `['direction' => 'higher'|'lower'|'equal', 'band' => 'freezing'|'warm'|'hot'|'nailed', 'won' => bool]`.
- `won = $guess === $answer` → band `nailed`, direction `equal`.
- else `direction = $guess < $answer ? 'higher' : 'lower'`; `pct = abs($guess-$answer)/$answer`; `pct > .25 → freezing`, `pct >= .10 → warm`, else `hot`.
- **Returns the band only — never `pct` or the distance.** Called only server-side.

**`today(): ?DropPricePuzzle`** — `Cache::remember('dropprice.today', ttl, fn)` on the **database** cache store, keyed/TTL'd to bust at the UTC day boundary, wrapped in `try/catch` (the `ArticleBody`/`NavigationData` pattern, so pure unit tests without a bound cache still work). Falls back to the latest puzzle if today's is missing.

**Verify:** pure unit test of `evaluate()` across band boundaries (exact, 9%, exactly 10%, exactly 25%, 26%) and both directions.

---

## Phase 4 — Livewire component + view (the secrecy boundary) ✅ DONE

**Goal:** The interactive game — tracks 4 guesses server-side, never renders the price pre-reveal, reuses the Subscriber flow to save, shows the reveal CTA.

**Built:** `app/Livewire/DropPrice.php` + `resources/views/livewire/drop-price.blade.php`. The secret answer is **never a public property** — `guess()` reads it on demand via `DropPrice::today()->price`, scores it through `DropPrice::evaluate()`, and pushes only an ordinal row (`['guess','direction','band']`) onto the public `$results`. `revealPrice`/`affiliateProductId` stay `null` until the game finishes (a win, or `MAX_GUESSES` reached); only then are they set and a `dropprice-finished` event (ordinal data only — won/number/guesses/results) is dispatched for Phase 5's Alpine. `guess()` validates `required|integer|min:1|max:100000` and no-ops once finished. `save()` mirrors `JoinTheDrop` (firstOrCreate by email → `success`/`duplicate`/`error`) then `syncStreak()`: client streak counters are sanitized (`max(0,(int))`), bests/totals `max`-merged, and written with **`forceFill()->save()`** because the streak columns are intentionally non-fillable (a plain `update()` silently drops them — caught in testing); if the game is finished it upserts today's `drop_price_results` row with **server-computed** `won`/`guesses_used`/`closest_miss_pct` (the miss % is derived from the player's own stored guesses, never shipped as a live hint). View reveals the price only inside `@if($finished)`; CTA is `route('affiliate.redirect', $affiliateProductId)` `target="_blank" rel="nofollow sponsored"` + `<x-affiliate-disclosure>`; product image via `<x-responsive-image>`; band colour classes written literally (`bg-sky-100`/`bg-amber-100`/`bg-rose-100`/`bg-emerald-100` + rings) so the Tailwind purge keeps them. Verified by `tests/Feature/Livewire/DropPriceTest.php` (8 tests — mount hides price, wrong guess stays ordinal + hidden, exact guess reveals + CTA + dispatch, 4-wrong loss reveal, post-finish no-op, 0/negative validation, save subscribes + result row, duplicate syncs without a 2nd subscriber). Suite green at **84 tests**.

**New files:** `app/Livewire/DropPrice.php`, `resources/views/livewire/drop-price.blade.php`.

**Public (client-visible) props:** `?int $guess`, `array $results` (each `['guess','direction','band']` — **no price**), `bool $finished`, `bool $won`, `int $puzzleNumber`, `string $productName`, `string $productImage`, reveal-only `?int $revealPrice = null` + `?int $affiliateProductId`, plus email-save `#[Validate('required|email|max:255')] string $email` and `?string $saveStatus`.
**The secret answer is NOT a public property** — it's read on demand inside the action via `DropPrice::today()->price`. `revealPrice` stays `null` until `finished` (public props serialize into the Livewire snapshot sent to the browser).

**`mount()`** loads display data (number/name/image) only — no price.

**`guess()`**: guard if finished/≥4; `validate(['guess' => 'required|integer|min:1|max:100000'])`; `evaluate($this->guess, (int) DropPrice::today()->price)`; append ordinal result; set win/finish; on finish set `revealPrice` + `affiliateProductId` and `dispatch('dropprice-finished', won, number, results)` for Alpine; reset `$guess`.

**`save()`** mirrors `app/Livewire/JoinTheDrop.php` exactly (find-or-create `Subscriber` by email, `token => Str::uuid()`, `ip_address => request()->ip()`, try/catch → `success`/`duplicate`/`error`). Then persist the client's streak counters onto the subscriber (`max()`-merged) and upsert today's `drop_price_results` row.

**View** renders product image via **`<x-responsive-image>`** (never raw `<img>`), name, "Drop Price #{number}", the guess input (`wire:model`, `inputmode="numeric"`, `wire:submit="guess"`, `wire:loading` states), and the ordinal result rows (direction arrow + band emoji). **No price text anywhere until `@if($finished)`**, which reveals price + win/loss message + affiliate CTA: `route('affiliate.redirect', $affiliateProductId)`, `target="_blank"`, **`rel="nofollow sponsored"`**, framed "only $X — cheaper than you guessed!", with the standard affiliate disclosure. Email-save card shown on win OR when Alpine signals streak ≥ 2 (mirrors join-the-drop success/duplicate/error UI).

**Must NOT be in the DOM pre-reveal:** the price (int or decimal), the exact distance, any width/style/data-attr derived from distance, the answer in the Livewire snapshot, the product's `affiliate_url`/`price`. Only image, name, number, ordinal band/direction.

**Verify:** Livewire tests (Phase 8), incl. `assertDontSee($price)` pre-finish.

---

## Phase 5 — Client state: Alpine + localStorage ✅ DONE

**Built:** Registered a `dropPrice` Alpine component in `resources/js/app.js` inside the existing `alpine:init` handler (after `heroCarousel`; no `Alpine.start()`), and wired the Livewire view `resources/views/livewire/drop-price.blade.php` to it — `x-data="dropPrice({ number, max, shareUrl })"` on the root + `@dropprice-finished.window="onFinished($event.detail)"`. Single localStorage key `gadgetdrop_dropprice` (versioned `v:1`, deep-merged onto a `fresh()` default so added fields are forward-safe). **Lockout:** `init()` sets `alreadyPlayed` when `lastPlayedNumber === number`; the Blade hides the live input (`x-show="!alreadyPlayed"`) and shows an Alpine lockout card (restored emoji rows + "next drop #n+1", **no price** — we never stored it). **Streak math** in `onFinished()`: UTC day key (`toISOString().slice(0,10)`), consecutive-day `++` / gap reset to 1 / same-day no-op (idempotent on `lastPlayedNumber`), win streak `++`/reset, bests via `Math.max`. **Email prompt** is a `showSavePrompt` getter (`won || playStreak >= 2`) gating the save card; the form submits via `x-on:submit.prevent="$wire.save(stats)"` so the localStorage counters flow straight into the server `save(array $stats)` action (matches the test's `->call('save', [...])`). **Share** is spoiler-free (`Drop Price #N 🎯 g/4` + band-emoji row + URL) via `navigator.share` → `navigator.clipboard.writeText` fallback, `copied` for 2s (mirrors `shareBar`). **Secrecy held:** Alpine only ever receives ordinal data (won/number/guesses/band rows); `closestMissPct` was intentionally dropped from the client (computing it needs the answer — the server already stores it in `drop_price_results`). **`wire:navigate` safe:** declarative `.window` x-on binding (auto-cleaned); the lone manual handle (the `copied` timer) is cleared in `destroy()`. Not yet on the homepage — that's Phase 6. Full suite green at **84 tests** (the Livewire `DropPriceTest` renders this view, so the Blade restructure + `$wire.save(stats)` rewire are covered).

**Goal:** Anonymous streak, one-play-per-day lockout, emoji UI, share/clipboard — client-side, cooperating with Livewire without leaking the answer, surviving `wire:navigate`.

**Modify:** `resources/js/app.js` — register a `dropPrice` Alpine component **inside the existing `alpine:init` handler** (alongside `heroCarousel`). **Never call `Alpine.start()`.**

- **localStorage** (single JSON key `gadgetdrop_dropprice`): `lastPlayedNumber/Date`, `playStreak`, `bestPlayStreak`, `winStreak`, `bestWinStreak`, `totalPlays`, `totalWins`, `closestMissPct`, `lastResultEmoji`.
- **Lockout:** on `init`, if `lastPlayedNumber === puzzleNumber` → show finished/result + share instead of the input. (UX nicety, not security — the answer never ships, so replaying gains nothing.)
- **Play-streak math** on the `dropprice-finished` event: consecutive day → `++`, gap → reset to 1, same day → no-op; win streak `++` on win, reset on loss; track bests.
- **Email prompt** shows when `won` (from Livewire) OR `playStreak >= 2` (Alpine); submit routes to the Livewire `save()` passing the counters.
- **Share:** build spoiler-free block (`Drop Price #142  🎯 3/4` + emoji rows + URL) → `navigator.clipboard.writeText`, `copied` for 2s (mirror existing `shareBar`).
- **Secrecy:** Alpine only ever receives `won`/`number`/**emoji rows** (already ordinal) via the Livewire event — never the price. Post-reveal it may read the price Livewire rendered.
- **`wire:navigate` safety:** prefer `@dropprice-finished.window` x-on bindings in Blade (auto-cleaned by Livewire teardown); if a manual listener is added in `init`, it **must** be removed in `destroy()` (the repo's documented leak pattern, cf. `readingProgress`).

**Verify:** manual smoke on `/` — fresh vs returning localStorage, lockout, streak increment across simulated days, share text correct, navigate-away-and-back doesn't double-bind.

---

## Phase 6 — Homepage integration (60/40 grid)

**Goal:** Restructure the hero into desktop 60% carousel / 40% game; mobile carousel-first — without breaking the carousel; pass the puzzle (image/name/number only — **no price**) from the controller.

**Modify:** `resources/views/public/home.blade.php` (hero section, lines 37–108) and `app/Http/Controllers/PublicController.php` (`home()`).

**Controller:** after building `$heroSlides`, add a display-only array (the Livewire component re-reads `DropPrice::today()` for the answer itself):
```php
$puzzle = \App\Support\DropPrice::today();
'dropPrice' => $puzzle ? ['number'=>$puzzle->puzzle_number,'name'=>$puzzle->product_name,'image'=>$puzzle->product_image_url] : null,
```
**Never** pass `price`.

**Layout:** wrap the existing `<section x-data="heroCarousel(...)">` and a new game column in a full-bleed responsive grid:
```blade
<div class="grid grid-cols-1 lg:grid-cols-5">
  <section class="lg:col-span-3 relative overflow-hidden min-h-[460px] md:min-h-[520px] ..." x-data="heroCarousel({{ $count }})"> {{-- existing carousel unchanged --}} </section>
  <div class="lg:col-span-2">
    @if($dropPrice) @livewire('drop-price', ['number'=>$dropPrice['number'],'name'=>$dropPrice['name'],'image'=>$dropPrice['image']]) @endif
  </div>
</div>
```
- `grid-cols-1` (mobile: carousel then game = carousel-first) → `lg:grid-cols-5` with `col-span-3`/`col-span-2` = 60/40 desktop.
- Carousel internals (absolute `inset-0` slides, swipe, arrows `hidden lg:flex`, dots) unchanged — width now derives from the grid cell; the inner `max-w-4xl mx-auto` re-centers cleanly. Parent stays `relative` so arrows/dots still anchor.
- Update the carousel image `sizes` and the LCP preload `imagesizes` from `100vw` toward `~60vw` on `lg` for hint correctness.
- Give the game column its own dark panel matching the hero bg so the 60/40 seam reads intentionally.

**Risk — LCP:** the hero image is the LCP; keep it `fetchpriority="high"`, keep the (lightweight, image-free) game column un-lazy. Livewire is already loaded site-wide, so the island adds little.

**Verify:** load `/` at mobile + `lg` — carousel above game on mobile, 60/40 desktop, swipe/arrows/dots work; **`view-source` grep confirms the price is absent**; smoke `/admin`.

---

## Phase 7 — Admin preview/override (minimal in v1; full UI later)

**Goal:** Let an admin preview/override the upcoming puzzle. Admin is React/Inertia under `/admin` — keep small.

**v1 slice:** the `dropprice:lock --product=ID --date=YYYY-MM-DD` CLI override (built in Phase 2) + optionally a read-only "Today's Drop Price: #142 — {product} — ${price}" panel on the existing admin dashboard (admin-only, so showing price is fine).

**Later phase:** a full Inertia screen to queue/preview/re-roll puzzles with a product picker (sets `is_preset` rows) — deferred, not needed to launch.

---

## Phase 8 — Tests (keep the sqlite suite green)

- **`tests/Unit/DropPriceTest.php`** — `evaluate()` band boundaries + direction symmetry.
- **`tests/Feature/Livewire/DropPriceTest.php`** — mount hides price (`assertDontSee`); wrong guess keeps it hidden; exact guess → `won`/`finished` + reveal price + CTA route; 4 wrong → loss reveal; `save()` valid → `success` + `assertDatabaseHas('subscribers')` + result row; duplicate → `duplicate`, no 2nd subscriber; invalid/decimal/0/negative guess → `assertHasErrors`; `guess()` after finish is a no-op.
- **`tests/Feature/DropPriceCommandTest.php`** — eligibility filtering, sequential number, one/date, integer price, idempotency, `--product`/`--date` preset.
- **Extend `PublicPagesTest`** — `/` with a seeded puzzle returns 200, renders name + "Drop Price #", and **does not contain the price string**.

**Verify:** `php artisan test` fully green on sqlite.

---

## Phase 9 — Build / deploy / caching

- **Tailwind purge:** band emojis are content (safe), but any band-keyed **color classes** (e.g. `bg-sky-100 / bg-amber-100 / bg-rose-100 / bg-emerald-100` for the meter) must appear **literally** in Blade or `app.js` so the purge scan keeps them. `npm run build` after editing Blade/JS/CSS.
- **DB-cache:** `DropPrice::today()` uses `Cache::remember` on the database store (no Redis); the lock command + any override `Cache::forget('dropprice.today')`; TTL must not outlive the UTC day. `cache:prune-expired` (05:00) already sweeps it.
- **Schedule at :00:** `dropprice:lock` → `dailyAt('00:00')` UTC (hourly-cron compliant). Locks within the first UTC hour; homepage falls back to latest puzzle meanwhile.
- **`wire:navigate`:** the `dropPrice` Alpine component cleans up in `destroy()` (or uses `.window` x-on bindings); the Livewire island re-mounts per swap without duplicate listeners.
- **Smoke both `/` and `/admin`**; grep rendered `/` HTML to confirm the price never appears pre-reveal.

---

## Open items to confirm during build (low-risk defaults chosen)
1. **Answer rounding:** round-to-nearest-dollar (so odd-cent prices are still winnable). Documented on the game.
2. **Price drift:** the reveal shows the **snapshot** price (the game's answer), which may differ from Amazon's live price later that day — acceptable for a daily game.
3. **Client-supplied streak counters** are advisory bragging stats, `max()`-merged server-side (no leaderboard in v1, so no anti-cheat needed).
4. **UTC midnight reset** is global (users far from UTC reset mid-day) — acceptable for v1; per-TZ is a later phase.

## End-to-end verification (whole feature)
1. `php artisan migrate` then `php artisan dropprice:lock` → one puzzle for today with an integer price.
2. `npm run build`; load `http://gadget-drop.test/` → 60/40 hero on desktop, carousel-first stack on mobile; **`view-source` shows no price**.
3. Play: wrong guesses show 🥶/😊/🔥 + higher/lower; exact guess shows 🎯, reveal price, and the Amazon CTA (`/out/{product}`); share button copies a spoiler-free block.
4. Win → "Save your streak" → enter email → `subscribers` row created; reload → lockout state persists (localStorage).
5. `php artisan test` green; smoke `/admin`.
