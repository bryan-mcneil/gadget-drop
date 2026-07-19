# Plan 01 — Verdicts Everywhere + 30-Day Reference Price

**Goal:** the verdict system that already lives inside `<x-price-history>` becomes a site-wide, reusable language — verdict chips on product cards and /deals, an Omnibus-style "lowest price in the last 30 days" reference line, and a public methodology section that both humans and answer engines can cite.
**Size:** S–M · **Branch:** `feature/verdicts-everywhere` · **Depends on:** Plan 00 (Dusk, for Phase 1.5)
**Non-goals:** changing PriceIntel math or honesty gates (they are correct and tested); adding any new CTA (single-CTA rule holds); price-drop emails (Plan 03).

Context for the implementer: `resources/views/components/price-history.blade.php` already renders verdict badges from `PriceIntel::stats()` (`lowest|good|typical|elevated`), and `/deals` (`DealsController`) already filters on verdicts. This plan **extracts, extends, and surfaces** — it does not invent.

## Phase Log

- [x] Phase 1.1 — Extract `<x-verdict-badge>` + add 30-day reference line (commit: fb94717)
- [x] Phase 1.2 — Product-card verdict chip (commit: 218dc83)
- [x] Phase 1.3 — /deals adopts the shared badge + reference (commit: 37437f4)
- [ ] Phase 1.4 — "How we call deals" methodology + FAQ JSON-LD (commit: )
- [ ] Phase 1.5 — Dusk pass (commit: )

## Design decisions

- **One badge component, size variants.** The verdict→label/classes map moves out of `price-history.blade.php` into `resources/views/components/verdict-badge.blade.php` (`@props(['verdict', 'dropPct' => null, 'size' => 'md'])`). Full Tailwind class strings stay literal in the component (purge safety — CLAUDE.md Build gotchas).
- **The 30-day reference line is the Omnibus move.** EU law forces retailers to disclose the prior-30-day lowest price; the US has no equivalent. We show `low30` (already computed, currently unused by any template) as: *"Lowest price in the last 30 days: $X"*. Voluntary compliance as brand.
- **Copy tone:** price *context*, never accusation. "Higher than usual" ✓ — "fake discount" ✗ on product surfaces (that language is reserved for Truth Report editorial, Plan 05).
- **Verdict chips link to methodology** (`/how-we-review#deal-verdicts`), an internal informational link — the product card's single affiliate CTA is untouched.

---

## Phase 1.1 — Extract `<x-verdict-badge>` + 30-day reference line

**Scope:** new Blade component; `price-history.blade.php` refactored to use it; reference line added.

Steps:
1. Create `resources/views/components/verdict-badge.blade.php`. Move the `$verdictBadge` map verbatim (labels + classes). Props: `verdict` (string|null — render nothing when null/unknown), `dropPct` (float|null — when ≥1 and verdict is `lowest|good`, append "· {pct}% below typical"), `size` (`sm` = current deals-card scale, `md` = current widget scale; map to literal class strings, no interpolation).
2. Refactor `price-history.blade.php` to `<x-verdict-badge :verdict="$stats['verdict']" :drop-pct="$stats['drop_pct']" />`. **Reminder:** bound `:` attributes only — no Blade directives between component attributes (CLAUDE.md Blade gotcha; this exact class of bug once blanked every hero image).
3. Add the reference line inside the existing `has_stats` footer block of the widget: `Lowest price in the last 30 days: ${low30}` with a `title` attr: "The EU requires retailers to disclose this. We do it voluntarily." Uses `$stats['low30']` — no PriceIntel changes.
4. `npm run build` (new classes must be scanned); `php artisan view:clear` if anything looks stale.

Tests (extend `tests/Feature/PriceHistoryWidgetTest.php`):
- Badge renders for each verdict tier (data-provider over the four verdicts).
- Badge absent when gates unpassed (`has_stats` false) — assert the 30-day line is ALSO absent then.
- Reference line shows the `low30` figure when stats exist.
- Regression: existing widget assertions still green unchanged.

Commit: `feat(verdicts): extract x-verdict-badge + Omnibus-style 30-day reference line`

Review checklist: no dynamic class interpolation; component renders empty (not broken) on null verdict; no new CTA/link to Amazon anywhere; widget DOM structure preserved where the drop-cap/post-body CSS could care (it doesn't touch `.post-body`, but verify the review page still renders).

## Phase 1.2 — Product-card verdict chip

**Scope:** the review-page product card (`resources/views/components/` — locate `x-product-card`) gains a small verdict chip near the price + a quiet "How we call deals" link to `/how-we-review#deal-verdicts`.

Steps:
1. Find the product card component (grep `product-card`); confirm where `PriceIntel::stats()` is already available on the review page (the widget gets `$stats` — trace who passes it; reuse the same source, do NOT call `PriceIntel::stats()` twice per page... it's cached, but pass the array down for clarity).
2. Add `<x-verdict-badge size="sm" …/>` beside the price; below it (text-xs, gray) the methodology link. `wire:navigate` on that internal link per site convention.
3. Only render when verdict non-null — a card with no chip must look exactly as today (flex/grid: keep `min-w-0` discipline if touching the layout — CLAUDE.md responsive conventions).

Tests: feature test on a review page (`PublicPagesTest` pattern or a new `VerdictSurfacesTest`): seeded product w/ qualifying snapshots shows chip; product without stats shows no chip and no methodology link; the card's affiliate link count is still exactly 1 (single-CTA assertion — count `route('affiliate.redirect')` occurrences in the card region).

Commit: `feat(verdicts): verdict chip on the review product card`

Review checklist: single-CTA rule verified by test, not by eye; `rel` attributes untouched; no layout overflow on mobile (spot-check 375px).

## Phase 1.3 — /deals adopts the shared badge + reference

**Scope:** `resources/views/public/deals.blade.php` uses `<x-verdict-badge>`; each deal card shows "30-day low $X"; page copy tightened to name the standard.

Steps:
1. Swap any bespoke verdict markup on deal cards for the component (`size="sm"`).
2. Add `low30` to each card's stat row. `DealsController::buildFeed()` must pass `low30` through (add the key to the `$deals[]` array — it's already in `$stats`). **Cache note:** bump/forget the `deals.feed` cache key on deploy (it caches the OLD array shape for up to 1h; the Blade must null-coalesce `$deal['low30'] ?? null` to survive one stale hour, or `Cache::forget('deals.feed')` in the deploy smoke checklist — do the null-coalesce AND note the forget).
3. Intro copy: one sentence naming the practice — "Every drop is measured against our own recorded history, including the lowest price of the last 30 days — the disclosure EU law requires and US law doesn't."

Tests (extend `tests/Feature/DealsPageTest.php`): card shows badge + 30-day low; feed array contains `low30`; stale-shape tolerance (render with a deal array missing `low30` → no error).

Commit: `feat(verdicts): shared badges + 30-day low on /deals`

Review checklist: feed still honesty-gated (no change to qualifying rules); `deals.feed` TTL unchanged (1h); copy has no legal overreach (we "do voluntarily", we don't claim compliance regimes).

## Phase 1.4 — "How we call deals" methodology + FAQ JSON-LD

**Scope:** a `#deal-verdicts` section on `/how-we-review` + FAQPage JSON-LD on that page. This is the citable asset: humans trust it, answer engines quote it, Plans 04/05 link to it.

Steps:
1. Locate `resources/views/public/static` how-we-review view + its `PublicController::howWeReview()` method. Add a section explaining, in plain language: snapshot sources; the honesty gates (≥2 snapshots spanning ≥14 days; flat price = "typical", never "lowest"); the four verdicts with their exact thresholds (5% vs 90-day average); the 30-day reference line and why (EU-required, US-voluntary); what we never do (MSRP theater, scraping).
2. Add FAQPage JSON-LD via the existing `serverJsonLd` share pattern (see home/post controllers): 4–5 Q/As mirroring the section ("What does 'Lowest tracked price' mean on GadgetDrop?" etc.).
3. Anchor `id="deal-verdicts"` for the chip links from Phases 1.2/1.3.

Tests: `PublicPagesTest`-style: page renders new H2; response contains `application/ld+json` with `FAQPage`; anchors resolve (assert `id="deal-verdicts"` present).

Commit: `feat(verdicts): public deal-verdict methodology + FAQ structured data`

Review checklist: JSON-LD validates (paste into validator during review); claims match PriceIntel constants EXACTLY (`MIN_POINTS`, `MIN_SPAN_DAYS`, `DEAL_PCT` — quote the constants, don't hand-type numbers that can drift: cite values via a small `@php` pull from the class so copy can't lie).

## Phase 1.5 — Dusk pass

**Scope:** browser truth for the three surfaces.

Tests (`tests/Browser/VerdictSurfacesTest.php`): seed product + snapshots spanning the gates (freeze time helpers in the seeder) → review page shows chip text; /deals shows badge + 30-day low; methodology anchor navigates. One test per surface, keep it lean.

Commit: `test(verdicts): Dusk coverage for verdict surfaces`

## Testing summary

Unit: none needed (no math changed). Feature: widget, card, deals, methodology (≈8–10 new tests). E2E: 3 Dusk tests. Suite target: 181 → ~190 green.

## Deployment

Standard template. Deltas: no migrations; step 6 adds `/deals`, `/how-we-review#deal-verdicts`, one review page with stats; add `php artisan tinker --execute="Cache::forget('deals.feed');"` on the server right after deploy (feed shape change).

## Maintenance

- Quarterly: review verdict thresholds against accumulated data (`DEAL_PCT` 5% may deserve tiering by category once volume exists) — a `/gd-health` prompt, decision logged here.
- When Truth Report (05) ships, link it from the methodology section.
- Copy audit each sale season: the "EU/US" sentence must stay legally accurate — re-verify annually.

## Risks

- **Stale compiled Blade masking the new classes** — the known Tailwind/storage-views gotcha; `view:clear` + rebuild is in Phase 1.1.
- **Chip clutter on small cards** — if the sm badge crowds 375px layouts, drop `dropPct` at `sm` size rather than shrinking text further.

## Build Log

(append one line per phase: date · what happened · surprises)

- 2026-07-19 · Phase 1.3 · /deals: bespoke "Lowest price we've tracked." span → shared `<x-verdict-badge size="sm">` (no `:drop-pct` — the card's −N% pill carries the magnitude); stat row gains null-guarded "30-day low $X" (`low30` passed through `buildFeed()`; `??` guard covers the ≤1h stale-cache window, worth_pct precedent); Omnibus intro sentence folded in em-dash-free (Bryan's 65a87e3 editorial pass); hero How-We-Review link retargeted to `#deal-verdicts` as a plain link (fragment convention). DealsPageTest +3; suite 347→350. gd-code-reviewer APPROVE WITH NITS (commit-message nit only). `public/build` intentionally untouched: initial `gap-y-0.5` wasn't in the bundle, switched to `gap-y-1` (verify-then-revert held). SURPRISE: the committed bundle carries 7 dead utility rules (`.-mt-px .ml-12 .ml-4 .border-r .border-gray-400 .leading-7 .text-black`) because the 1.2 build ran without `view:clear` first (stale compiled views fed the Tailwind scan); none are dynamically composed, so it clears itself on the next clean rebuild — 1.4/1.5 implementer: run `php artisan view:clear` BEFORE `npm run build`.
- 2026-07-19 · Phase 1.2 · Card verdict chip + "How we call deals" methodology link, both gated on non-null verdict (read from `$product['price_intel']` — no second `stats()` call). New `VerdictSurfacesTest` ×3; suite 344→347. gd-code-reviewer APPROVE WITH NITS, 0 blockers; WARN applied per this plan's Risks: the card chip omits `:drop-pct` (nowrap "· N% below typical" suffix overflows the ~167px column beside the card image at 375px; the widget below carries the magnitude). Divergences ratified: methodology link is a PLAIN link, not `wire:navigate` — fragment targets need native navigation to scroll (truth/show precedent; carry this into Phase 1.3's chip links); committed on main (no-feature-branches agreement, 2026-07-18). `public/build` rebuilt & committed — `.w-fit`/`.gap-x-2` were absent from the bundle; the rebuild also repaired deals.blade.php's latent uncompiled `w-fit`. NOTE for 1.4: Plan 04 already created the `#deal-verdicts` section ("How our price data works", anchor cited by the MCP methodology resource — keep stable); 1.4 should extend it in place, not create a new section.
- 2026-07-13 · Phase 1.1 · Extracted `<x-verdict-badge>` (verdict→label/class map moved verbatim from `price-history.blade.php` + `sm`/`md` literal-class size variants, purge-safe); refactored the widget to use it and added the Omnibus 30-day reference line ("Lowest price in the last 30 days: $X", using the already-computed-but-unused `low30`). Suite 262→270 green. gd-code-reviewer: APPROVE WITH NITS, 0 blockers (applied the low30-vs-low90 test-isolation fix). **Decision to ratify:** removed the old footer "% below typical" span so the badge doesn't double-render the magnitude — narrow side effect is a `typical` verdict sitting 1–5% below its 90-day avg no longer shows that note (arguably a copy improvement). NIT surfaced: the `good` tier reads "Below typical price · N% below typical" (double "typical") — kept the plan's literal string. Surprises: (1) local `npm run build` non-deterministically rehashes the ENTIRE `public/build` bundle (env/toolchain drift — committed build & source last shipped together in 042050a, yet rebuilding unchanged admin source yields byte-different chunks); verified the new classes compile into the committed CSS then reverted the churn (prod-safe: `bin/deploy.sh` ships the committed bundle and every new decl was already present). (2) Committed outside the normal flow during `/morning` as `fb94717` "Price history review" (not the prepared `feat(verdicts): …` message); reviewer agent-memory (`project_build-revert-verification.md`) rode along in that commit.
