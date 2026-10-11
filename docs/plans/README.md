# GadgetDrop Feature Plans

Twelve implementation plans, each broken into small, individually reviewable phases. Work them **one phase at a time** with `/implement-phase <plan-file> <phase>` — never implement two phases in one session.

## The plan set (suggested order)

| # | Plan | Size | Depends on |
|---|------|------|-----------|
| 00 | [CI & testing foundation](00-ci-and-testing-foundation.md) | S | — |
| 01 | [Verdicts everywhere + 30-day reference](01-verdict-everywhere.md) | S–M | 00 (Dusk) |
| 02 | [Worth-It voting](02-worth-it-voting.md) | S–M | 00 |
| 03 | [Post-purchase price watch](03-post-purchase-watch.md) | M | 00, 01 |
| 04 | [Price-Truth MCP server](04-price-truth-mcp.md) | M | 01 |
| 05 | [Truth Report pipeline](05-truth-report.md) | M | 01 |
| 06 | [Buy-or-Wait engine](06-buy-or-wait.md) | L | 01, 04 (last phase) |
| 07 | [Automated review videos → YouTube (drop-studio)](07-video-pipeline.md) | L | — (new sibling repo; consumes daily-drop output) |
| 08 | [Market → catalog promotion](08-market-promote.md) | S | — (market layer on main) |
| 09 | [Mention-driven review selection](09-review-queue.md) | M | — (market layer + reviewed-products API on main) |
| 10 | [Design refresh: price-truth instrument](10-design-refresh.md) | L | 01, 03, 06 (all shipped) |
| 11 | [Live price compare (Claude + web search)](11-live-price-compare.md) | M | — (price-intel layer + ClaudeExplainService on main) |

Strategy recap: GadgetDrop becomes the honesty layer for gadget prices — for humans (badges, votes, post-purchase protection, truth reports) and for AI agents (MCP). All features run structurally $0 on current hosting.

## Working agreement (applies to every phase)

1. **One phase per session.** Read the plan header + the target phase + `CLAUDE.md` before touching code. If reality diverges from the plan (file moved, API changed), STOP and report the divergence — don't improvise silently.
2. **Branch: `main`.** Standing decision 2026-07-18: no more `feature/*` branches — commit phases directly on the current branch (normally `main`). Plans 00–06 predate this and their headers still name a `feature/<plan-slug>` branch; those branches are gone and the work landed on `main`. Treat the header branch on any plan as historical, not an instruction.
3. **Tests are part of the phase.** A phase without its listed tests is not done. Full suite must be green (baseline: 553 tests) before review.
4. **Review before commit.** Run the `gd-code-reviewer` subagent on the diff. Fix BLOCKERs, judge WARNs, then prepare the commit.
5. **Commits are prepared, not pushed.** Stage + present the commit message; Bryan approves the actual commit/push (per CLAUDE.md).
6. **Update the Phase Log** in the plan file (checkbox + one-line build note + commit hash) as the final step of every phase.

## Commit convention

```
type(scope): imperative subject ≤ 72 chars

- what changed and why (1–4 bullets)
- notable decisions or trade-offs

Plan: docs/plans/NN-name.md §Phase N.M
```

Types: `feat` `fix` `test` `refactor` `chore` `docs` `ci`. Scope = plan slug (`verdicts`, `voting`, `watch`, `mcp`, `truth`, `buywait`, `ci`). One commit per phase by default; split only when the plan says so.

## Definition of done (every phase)

- [ ] Code matches phase scope — nothing more
- [ ] Listed unit/feature tests written and green; full suite green
- [ ] Dusk test written when the phase says so (`php artisan dusk` locally)
- [ ] `gd-code-reviewer` verdict: APPROVE (or APPROVE WITH NITS with nits noted in commit body)
- [ ] `npm run build` clean when Blade/CSS/JS touched (then `php artisan view:clear` if classes look stale)
- [ ] Phase Log updated

## Shared deployment template

Referenced by each plan's Deployment section; deltas are listed per plan.

1. Local: full suite green → `npm run build` → commit `public/build` with the release commit.
2. Merge feature branch → `main` (PR if the cloud agent flow is active), push.
3. Server: `bash bin/deploy.sh` (pull, composer `--no-dev`, `migrate --force`, `optimize`, `images:optimize`).
3a. **Run the plan's data step, if it has one — `deploy.sh` does NOT run seeders.** A plan whose feature needs rows to exist (a seeder, a backfill, an import) ships dark without this: the code deploys, the pages 404, and any *ungated* cross-link to them becomes a live broken link. This is not hypothetical — Plan 06 sat dark on prod from 2026-07-23 to 2026-07-27 because `db:seed --class=ReleaseCycleSeeder --force` was never run, while the footer linked to its 404 from every page. If the plan's Deployment section names a seed/backfill command, it is part of the deploy, not an optional extra.
4. Verify Livewire update endpoint after `route:cache` (Join the Drop form submits OK).
5. Purge Hostinger CDN (hPanel → Performance → CDN).
6. Smoke test: `/`, `/deals`, one review page, `/admin` login, plus the plan's listed URLs.
7. Rollback for cache weirdness: `php artisan optimize:clear` on the server. Schema rollbacks: each plan lists its down-path.
8. **Local hygiene afterwards:** never leave `optimize`/`config:cache` active in the local checkout (DB-wipe guardrail — see CLAUDE.md Tests).

## Maintenance calendar (once features ship)

| Cadence | Task | Owner |
|---|---|---|
| Monthly | `/gd-health` sweep (suite, audits, budget guards, table growth, snapshot coverage) | Bryan + Claude |
| Quarterly | Verdict threshold review (01), buy-or-wait cycle data re-source (06) | Claude session |
| Per sale event | Truth Report runbook (05) | Bryan + Claude |
| Weekly (auto) | CI Dusk run on `main` | GitHub Actions |
