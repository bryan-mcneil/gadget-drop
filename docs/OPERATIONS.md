# GadgetDrop Operations — The Daily Hour

How the site runs day to day, what's automated, and what Bryan actually does. Content rules live in `CONTENT-GUIDELINES.md`; pipeline mechanics in `.claude/commands/daily-drop.md`.

## What runs without you

| When (UTC) | What | Where |
|---|---|---|
| Early morning, daily | Cloud agent generates the day's drafts → PR `drop/YYYY-MM-DD` | Claude scheduled routine (instructions: `daily-drop/CLOUD-AGENT.md`) |
| 00:00 | `dropprice:lock` — locks the daily Drop Price puzzle | Hostinger cron |
| 04:00 | `images:optimize` — backfills responsive image variants | Hostinger cron |
| 05:00 | `cache:prune-expired` | Hostinger cron |
| 06:00 | `prices:refresh` — PA-API → Canopy (budget-guarded) → no-op | Hostinger cron |
| Fri 14:00 | `newsletter:send` — weekly digest | Hostinger cron |

(Hostinger cron fires hourly at minute :00 — new tasks must be scheduled at :00.)

## Your daily hour (~45–60 min)

1. **`/morning`** (~5 min) — merges the day's PR, validates, imports drafts into **production**, runs the honesty QA gate, prints the checklist. No PR? It offers to run the pipeline locally instead.
2. **Review the drafts** (20–30 min) — fact-check prices and claims against the source/listing, tone pass, tighten anything that reads generic. 1–2 drafts per day, never more.
3. **Images** (~10 min) — hero + product shots via the post-form uploader (variants generate automatically).
4. **Publish + prices** (5–10 min) — publish when happy, then a quick `/admin/prices` pass on the stalest products (type the new price or hit "Unchanged").

Weekly, not daily: skim `/deals` for honesty, prune weird auto-created tags in `/admin/tags`, glance at Search Console.

**Fallbacks:** cloud agent didn't run → `/morning` runs the pipeline locally (adds ~15–20 min). SSH unavailable → paste `daily-drop/output.json` into the prod `/admin/daily-drop` Import page.

## Honest expectations

- **The hour buys consistency, not virality.** A new affiliate/content site typically needs **6–12 months of daily publishing** before organic traffic is meaningful. The compounding assets are the review archive, `/deals`, the price-history data, and the Drop Price game — no single post matters much.
- The daily hour covers **content ops only**. Growth work — backlinks, social distribution, newsletter growth, Search Console cleanup — is separate and worth a few extra hours a week if growth is the goal.
- **AdSense reapply** happens after the value-build checklist is done (trust pages, thin-page noindex, price-intelligence layer — see repo history) and there's a consistent multi-week publishing record across all three types. Ads stay off (`ADSENSE_ENABLED=false`) until approval.
- Cadence = **11 posts/week** (7 reviews, 2 tips, 2 news). If a day slips, skip it — never batch-publish backlog in one day; steady beats bursty for both Google and sanity.

## Prod deploy runbook (one-time: first deploy since the redesign)

Nothing has been pushed to prod since the redesign, so the first deploy carries the consolidation work:

1. Local: `npm run build`, commit `public/build` with the code, push `main`.
2. Server: `bash bin/deploy.sh` (pull, composer, migrate, optimize, images).
3. Server one-offs, in order (all support `--dry-run` where noted):
   `php artisan categories:consolidate` (dry-run first) → `php artisan categories:rehome` (dry-run first) → `php artisan authors:consolidate` → `php artisan content:fix-em-dashes` → `php artisan prices:backfill` (dry-run first).
4. Write 80–150 word descriptions for the six surviving categories in `/admin/categories`.
5. hPanel → Performance → CDN → purge cache.
6. Smoke: `/`, a review page, `/deals`, `/drop-price`, `/admin`, and the Join-the-Drop form (Livewire endpoint survives `route:cache`).
7. **Post-deploy cleanup (follow-up commit):** delete the now-spent one-off commands + their tests — `ConsolidateAuthor`, `ConsolidateCategories` (+ `ConsolidateCategoriesTest`), `RehomeCategoryItems` (+ `RehomeCategoryItemsTest`), `FixEmDashes`, `BackfillPriceSnapshots` (after confirming `product_price_snapshots` has `source=initial` rows). Keep `content:flag-claims` forever (QA gate).

## Ongoing deploys

Content flows through the pipeline (no deploy needed — `/morning` pulls content files onto the server). Code changes: build assets locally, commit, push, `bash bin/deploy.sh` on the server, purge CDN if assets/HTML changed.
