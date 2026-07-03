# /daily-drop — Content Pipeline Status & Router

The daily content pipeline is split into small steps so each one can run in a cheap, fresh session (use /handoff between steps) or unattended by the scheduled cloud agent (see `daily-drop/CLOUD-AGENT.md`). State passes through files in `daily-drop/`, never through conversation context.

## Weekly cadence (one review every day, plus…)

| Day | Content |
|---|---|
| Mon | review + tech news |
| Tue | review + tech tip |
| Wed | review |
| Thu | review + tech news |
| Fri | review |
| Sat | review + tech tip |
| Sun | review |

## Steps

| Step | Command | Input | Output |
|---|---|---|---|
| 1 | `/drop-research` | web + reviewed-products API | `daily-drop/research.md` (1 primary + 1 backup product) |
| 2 | `/drop-write N` (N = 1, or 2 if the primary was a dud) | `daily-drop/research.md` | `daily-drop/product-N.md` (ONE review, byline Bryan McNeil) |
| 2b | `/drop-tip` (Tue/Sat) or `/drop-news` (Mon/Thu) | web research, same-morning for news | `daily-drop/tip-1.md` / `daily-drop/news-1.md` |
| 3 | `/drop-assemble` | `daily-drop/*.md` | `daily-drop/output.json` via `php bin/daily-drop-build.php` |
| 4 | `php artisan posts:import` (or `/morning`, which wraps it for prod) | `daily-drop/output.json` | draft posts |

Editorial rules per type: `CONTENT-GUIDELINES.md`. Daily routine: `docs/OPERATIONS.md`.

## What to do when this command runs

1. **Detect state** (Glob `daily-drop/*`):
   - No `research.md`, or its `DATE:` is not today → next step is `/drop-research`
   - `research.md` current, no `product-*.md` → next step is `/drop-write 1`
   - Review written but today is a tip/news day (table above) and `tip-1.md`/`news-1.md` is missing → next step is `/drop-tip` or `/drop-news`
   - All of today's expected files exist, no fresh `output.json` → next step is `/drop-assemble`
   - `output.json` built today → pipeline done; next step is `php artisan posts:import` locally, or `/morning` to import into production

2. **Report**, in 3 lines or fewer: what's done, what's next, e.g.
   `Research done · review written · today is Tue → Next: /drop-tip (consider /handoff first)`

3. **Default: do not run the next step.** The split exists so the user can /handoff to a cheaper model between steps. Only execute steps yourself if the user explicitly says so (e.g. "/daily-drop run all"); in that case Read each step's command file from `.claude/commands/` and follow it exactly, one step at a time, in order.
