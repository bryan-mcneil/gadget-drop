---
name: partial-motion-guard
description: Recurring card-hover bug — the rail sweep gets motion-reduce guarded but the lift + image zoom don't; grep all hover:-translate-y / group-hover:scale on any motion phase
metadata:
  type: project
---

Card markup in this repo repeatedly ships a **partial** `prefers-reduced-motion` guard: the top-rail sweep carries `motion-reduce:transition-none` while the sibling `hover:-translate-y-*` lift and `group-hover:scale-*` image zoom are left unguarded. The old `post-card.blade.php` comment even asserted "the sweep is the only animation" — which was false (the lift + zoom also animate).

**Why:** Plan 10 Motion Budget is absolute ("card hover lift/sweep ... Everything behind `prefers-reduced-motion`"), but the guards are applied class-by-class with no automated test, so a per-element miss reads as done.

**How to apply:** On any motion/QA phase (10.6 and future), do NOT trust a "the only unguarded motion was X" claim — grep `hover:-translate-y` and `group-hover:scale` across `resources/views/**` and confirm each has its `motion-reduce:hover:translate-y-0` / `motion-reduce:group-hover:scale-100` sibling. **Resolved in 10.6:** the whole card system — `post-card.blade.php`, `author-post-card.blade.php`, and the Phase 10.3 signature Top Picks cards in `public/home.blade.php` (featured + compact wrappers `:221`/`:270` and their `<x-responsive-image>` zooms `:227`/`:276`) — is now fully guarded; the home Top Picks miss was the 10.6 BLOCKER and is fixed. Still unguarded on purpose (out of the 10.3 card scope): pre-existing listing zooms in `news`/`search`/`tag`/home category-strip/`drop-price` + the `worth-it-vote` lift — the reworded CLAUDE.md motion bullet now lists these as a "Not yet swept" forward convention rather than claiming site-wide coverage, so don't re-flag the doc as overclaiming. The three `motion-reduce:` variants already exist in the compiled public bundle, so adding them needs no `npm run build`.
