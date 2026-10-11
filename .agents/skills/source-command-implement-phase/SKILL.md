---
name: "source-command-implement-phase"
description: "Migrated source command `implement-phase`"
---

# source-command-implement-phase

Use this skill when the user asks to run the migrated source command `implement-phase`.

## Command Template

# /implement-phase — Execute exactly one phase of a docs/plans/ plan

You are implementing ONE phase of a GadgetDrop feature plan, to professional review standards, and then stopping. The plans were designed for this: small reviewable diffs, tests included, one commit, Bryan approves before anything lands.

## Usage

`/implement-phase <plan-file> <phase>` — e.g. `/implement-phase docs/plans/02-worth-it-voting.md 2.2`
If arguments are missing, run the `/plan-status` logic first and propose the next unchecked phase; confirm with Bryan before starting.

## Protocol (do not reorder, do not skip)

### 1. Load context
Read, in order: `AGENTS.md` (the constitution), `docs/plans/README.md` (working agreement + commit convention), the target plan's header + Design Decisions + the ENTIRE target phase, and the plan's Build Log (prior phases leave warnings there). Check the Phase Log: if a prior phase is unchecked, STOP and tell Bryan — phases assume their predecessors.

### 2. Verify ground truth
Confirm the phase's named files/symbols actually exist as the plan describes (plans were written against a snapshot; the repo moves). Spot-check the 2–3 most load-bearing assumptions with Read/Grep. **If reality diverges from the plan: STOP, report the divergence and a proposed adjustment, and wait.** Never improvise silently — the plan is the reviewed artifact.

### 3. Set up
Confirm you're on the plan's branch (`feature/<plan-slug>`; create from latest `main` if phase 1). Run `php artisan test` and record the green baseline count. If it's red before you start, STOP and report.

### 4. Restate the contract
Post a 4–6 line summary: phase scope, files you'll touch, tests you'll add, the commit message you expect to write. This is the last cheap moment for Bryan to redirect. Then create a task list (TodoWrite) mirroring the phase's steps and work it.

### 5. Implement
Only what the phase says. Follow the phase's steps in order — they encode gotcha-avoidance (build steps, cache forgets, guard checks). Match surrounding code style; when the plan references an exemplar (ContactForm's honeypot, ArticleBody's guarded cache), read the exemplar first and imitate it. If you notice an adjacent bug: note it for the report, don't fix it in this diff (unless the phase is blocked by it — then STOP and ask).

### 6. Test
Write every test the phase lists (delegate to the `gd-test-engineer` agent when the list is long or the fixtures are gnarly — give it the phase reference and behavior list). Run targeted tests, then the FULL suite; record `before → after` counts. Blade/CSS/JS touched → `npm run build` (and `php artisan view:clear` if classes act stale). Dusk phases → `php artisan dusk`.

### 7. Review
Launch the `gd-code-reviewer` agent on the working-tree diff with the plan reference. Fix every BLOCKER; apply or consciously decline WARNs (declines go in the commit body); re-run the suite after fixes. CHANGES REQUIRED → fix and re-review. Do not proceed on a red verdict.

### 8. Prepare the commit — and stop
Stage everything belonging to the phase (`git add` specific paths — never `-A` blindly; check `git status` for strays). Compose the message per the README convention (`type(scope): subject`, body bullets with the why + any declined WARNs, `Plan:` trailer). **Show Bryan: the diff stat, the full commit message, the suite counts, and the reviewer verdict. Do NOT run `git commit` or `git push` until Bryan says go** (AGENTS.md: commit only when explicitly asked).

### 9. Close the loop (after Bryan approves the commit)
Check the phase's box in the Phase Log with the commit short-hash; append one Build Log line (`YYYY-MM-DD · phase N.M · what happened · surprises/divergences`). These plan-file edits ride in the same commit or an immediate `docs(plan)` follow-up — Bryan's call.

## Hard rules

- One phase per invocation. If Bryan asks for "the next one too," finish this protocol first, then start a fresh /implement-phase.
- The suite is never left red at a stopping point.
- Divergence from plan = stop and surface. Plans get amended (edit the plan file, note it in Build Log), not silently outgrown.
- Deployment phases (each plan's Deployment section) are NOT run by this command — deploys are Bryan-driven via `docs/OPERATIONS.md` + `bin/deploy.sh`. When a plan's final phase completes, remind Bryan the plan's Deployment section is now actionable.
