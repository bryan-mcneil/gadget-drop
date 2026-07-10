# Plan 02 — Worth-It Voting (值 / 不值)

**Goal:** one-tap 👍 "Worth it" / 👎 "I'd skip" on every review and news post — SMZDM's proven engagement mechanic, no accounts, no moderation surface. Percentages become social proof on posts and /deals, and a ranking signal later.
**Size:** S–M · **Branch:** `feature/worth-it-voting` · **Depends on:** Plan 00
**Non-goals:** comments or any free-text input (nothing to moderate); user accounts; vote-based auto-ranking (observe first, rank later).

## Phase Log

- [ ] Phase 2.1 — Schema + model (commit: )
- [ ] Phase 2.2 — Livewire component (commit: )
- [ ] Phase 2.3 — Surfaces: post pages + /deals (commit: )
- [ ] Phase 2.4 — Dusk pass (commit: )

## Design decisions

- **Votes attach to `Post`** (reviews AND tips/news — "was this useful?" generalizes), not Product. Deals cards reach the vote % through their linked post (`post_id` is already in the deals feed array).
- **Identity = salted hash, no accounts:** `voter_hash = hash_hmac('sha256', session_id + '|' + ip, app.key)`. Unique index `(post_id, voter_hash)` is the double-vote gate; the session check is just UX. No raw IP stored (GDPR-clean, matches the site's privacy posture).
- **Small-sample honesty:** percentages display only at ≥ 5 votes ("Early votes — be the first" below that). Same brand rule as PriceIntel's gates.
- **Abuse posture:** RateLimiter (10 votes/min/IP) + the unique index. That's it — a skewed vote on a gadget review is low-stakes; don't build a fraud system.
- **`wire:navigate` safety:** Livewire components re-mount on navigate visits — no Alpine listeners needed, so no `destroy()` cleanup concerns.

---

## Phase 2.1 — Schema + model

**Scope:** migration + `WorthItVote` model + factory + `Post` relationship.

Steps:
1. Migration `create_worth_it_votes_table`: `id`; `post_id` FK `constrained()->cascadeOnDelete()`; `choice` string(8) (`worth`|`skip` — string not enum: sqlite tests + the enum-widening lesson from posts.type); `voter_hash` char(64); timestamps; `unique(['post_id', 'voter_hash'])`; `index(['post_id', 'choice'])`. Plain portable schema — no MySQL-only guard needed.
2. Model: `$fillable = ['post_id', 'choice', 'voter_hash']`; scopes `worth()`, `skip()`. Factory with realistic hash.
3. `Post::worthItVotes()` hasMany + a `worthItSummary(): array{worth:int, skip:int, total:int, pct:?int}` helper — pct null under 5 total (the honesty gate lives in one place, PHP-side, like PriceIntel).

Tests (`tests/Unit` or Feature — needs DB, so Feature: `WorthItVoteTest`): unique constraint enforced; summary math incl. the <5 null gate; cascade on post delete.

Commit: `feat(voting): worth_it_votes schema + Post summary helper`

Review checklist: no raw IP/PII columns; string choice (not DB enum); factory states for both choices.

## Phase 2.2 — Livewire component

**Scope:** `app/Livewire/WorthItVote.php` + `resources/views/livewire/worth-it-vote.blade.php` (class-based, matching `JoinTheDrop`).

Steps:
1. Component: `public Post $post; public ?string $voted = null;` — `mount()` computes `voter_hash` and preloads `$voted` if this hash already voted (page-refresh persistence). `vote(string $choice)`: validate in `['worth','skip']`; RateLimiter (`worth-it:{ip}`, 10/min — on limit, silently return current state, no error theater); insert with `insertOrIgnore` semantics (catch the unique-violation → treat as already-voted, load their existing choice); set `$voted`; `Cache::forget('deals.feed')` **only if** the post has a qualifying product (cheap: just always forget — the feed rebuilds hourly anyway and votes are low-frequency early on).
2. View: two buttons (thumb icons + labels), `x-cloak` not needed (server-rendered states), static initial classes with Livewire class toggles — **full literal class strings** for the selected/unselected variants (purge rule). After voting or if already voted: show "{pct}% of {total} readers say worth it" (or the early-votes line), buttons disabled/hidden. `@error` directive collision doesn't apply (no validation messages shown) — still, use `x-on:` for any Alpine event handlers if added.
3. Accessibility: `aria-pressed`, button labels readable without icons.

Tests (`tests/Feature/Livewire/WorthItVoteTest.php`, mirroring `JoinTheDropTest` style): vote records row with hashed identity; second vote same hash doesn't duplicate and reports original choice; invalid choice rejected; rate limiter kicks in (Livewire::withHeaders IP spoof or RateLimiter::clear in setup); summary line renders at ≥5 votes, early line below.

Commit: `feat(voting): WorthItVote Livewire component with hash identity + throttle`

Review checklist: no `Alpine.start()`; no raw IP persisted; class strings literal; component renders sanely for a guest with cookies disabled (session still issues an id — verify).

## Phase 2.3 — Surfaces: post pages + /deals

**Scope:** the component on every public post + read-only % on deal cards.

Steps:
1. `resources/views/public/show.blade.php`: mount `<livewire:worth-it-vote :post="$post" />` after the article body / near the share bar (below `<x-share-bar>` — engagement zone, away from the product card so the single affiliate CTA keeps its space).
2. /deals cards: `DealsController::buildFeed()` adds `worth_pct` + `worth_total` via the summary helper (one `withCount` pair on the posts eager-load — avoid N+1: add `withCount(['worthItVotes as worth_count' => fn($q)=>$q->where('choice','worth'), 'worthItVotes as skip_count' …])` to the existing posts constraint, compute pct in PHP). Blade: tiny "{pct}% say worth it" line when non-null; null-coalesce for the 1h stale-shape window (same trick as Plan 01 Phase 1.3).
3. Post cards elsewhere (home/category): NOT in this phase — observe engagement on the two surfaces first (note as future in Maintenance).

Tests: post page renders component (feature assertion on `worth-it-vote` marker); deals feed includes pct keys and respects the ≥5 gate; N+1 guard — assert query count on /deals doesn't grow with vote count (use `expectsDatabaseQueryCount` on a seeded feed... brittle: instead assert ≤ fixed sane number).

Commit: `feat(voting): voting on posts + worth-it social proof on /deals`

Review checklist: placement respects single-CTA zoning; no vote UI on /deals (read-only there); `deals.feed` forget noted for deploy.

## Phase 2.4 — Dusk pass

`tests/Browser/WorthItVoteTest.php`: visit seeded review → click "Worth it" → percentage/thanks state appears without reload; refresh → state persists (session hash); second browser session (fresh Dusk browser) votes independently.

Commit: `test(voting): Dusk coverage for vote flow + persistence`

## Testing summary

Feature: ~10 new tests (schema, component, surfaces). E2E: 2–3 Dusk tests. No unit-only tests (everything touches DB/session).

## Deployment

Standard template + `php artisan migrate --force` delta (one additive table — down path: `dropIfExists`, zero data risk on rollback since feature is new). `Cache::forget('deals.feed')` post-deploy. Smoke: vote on one prod post, refresh, confirm persistence.

## Maintenance

- `/gd-health` watches: votes/day trend, worth-vs-skip global ratio (a sudden 100%-skip wave on one post = possible griefing — manual look), table growth (trivial: two ints + hash per row).
- After ~60 days of data: decide on (a) extending to home/category post cards, (b) feeding `worth_pct` into /deals sort as a secondary key. Log the decision here.
- Votes on deleted posts cascade away automatically; no pruning job needed.

## Risks

- **Livewire update endpoint under route:cache** — the known caution (CLAUDE.md deployment notes); the post-deploy smoke test covers it (a vote IS a Livewire update call).
- **Session-less bots inflating one side** — rate limiter + unique-hash blunt it; accept residual noise at this stakes level.

## Build Log

(append one line per phase)
