---
name: plan06-buy-or-wait-review
description: Plan 06 retrospective review (commit eee9a02) — confirmed-safe patterns, the unseeded-prod class of bug, and standing follow-ups to re-check
metadata:
  type: project
---

Plan 06 (Buy-or-Wait) was reviewed retrospectively on 2026-07-27, after commit `eee9a02` had
already shipped to main and prod. Findings worth carrying forward:

**New failure class: code deployed, data seeder never run.** `/buy-or-wait` 404s on prod while the
footer link, `/deals` promo, `/how-we-review#buy-or-wait`, `llms.txt` and the MCP tool all ship
unconditionally. The index deliberately 404s when `release_cycles` is empty, so an unrun
`db:seed --class=ReleaseCycleSeeder --force` turns every entry point into a dead link.
**Why:** the plan's Deployment section carries a non-standard extra step (seed) that `bin/deploy.sh`
does not perform, and `DatabaseSeeder` does not register the seeder either.
**How to apply:** whenever a phase adds a seeder or any deploy-time data step, check whether the
new UI entry points are gated on that data existing; if not, that is a WARN at review time. On any
retrospective/already-merged review, probe prod with `curl` (status codes + a grep for the new
markup) to tell "code not deployed" from "deployed but data/cache step skipped" — that distinction
was the top finding here and is invisible from the diff.

**Cache invalidation paired with writes (CLAUDE.md) applies to editorial data too.** Per-cycle
verdict keys self-bust (they include `updated_at`), but the `/buy-or-wait` index key is
`version + date` only, and nothing flushes `sitemap.xml` / `search.llms_txt` on a cycle write. The
`Post::saved → NavigationData::flush()` pattern is the model to ask for.

**Silent-cap query smell.** `ReleaseCycle::flagshipProduct()` fetches the 50 newest reviewed
products and name-matches in PHP; for a cycle with no `category_id` that scans the whole catalog,
so the price half of the verdict silently disappears once a line's review ages past 50 products
(~6 weeks at 11 posts/week). Grep new support code for `limit(N)->get()` followed by an in-PHP
`first(fn ...)` filter — that shape degrades quietly instead of failing.

**Confirmed-safe here, don't re-flag:** `ReleaseCycle::$dateFormat` pinned to `'Y-m-d H:i:s'` (that
is what lets `Tests\Unit\BuyOrWaitTest` run with no container — same discipline as `ArticleBody`);
`BuyOrWait`'s guarded `Cache::remember` + compute fallback; the em-dash ban is genuinely enforced
in the generated copy (unit test asserts it, and every line this commit added to `deals`,
`for-ai`, `how-we-review`, `GadgetDropServer` is em-dash free — the counts in those files are all
pre-existing lines); Dusk `clickLink()` here is correctly followed by `waitForLocation`.

**Standing follow-ups (as of 2026-07-27, unfixed):** the seeder's iPhone `next_expected_note`
("September every year since 2012") is false for 2020 and is repeated verbatim in
`daily-drop/launch/news-buy-or-wait.md`; `BuyOrWaitController::metaDescription()` renders
"has been out 10." with no unit; index card confidence line uses `text-gray-400` on white (the
Plan 10 contrast rule postdates this commit).

Related: [[truth-report-gate]], [[honesty-gate-tri-surface]], [[dusk-clicklink-navigation-race]],
[[market-import-review]].
