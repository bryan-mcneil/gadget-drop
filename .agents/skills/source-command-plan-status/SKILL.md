---
name: "source-command-plan-status"
description: "Migrated source command `plan-status`"
---

# source-command-plan-status

Use this skill when the user asks to run the migrated source command `plan-status`.

## Command Template

# /plan-status — Where is every feature plan right now?

Scan `docs/plans/*.md` (skip README.md) and report a compact status board. Read-only; this command changes nothing.

## Steps

1. For each plan file, parse the **Phase Log** section: checked/unchecked boxes, commit hashes, phase titles. Parse the header line for size + dependencies, and the last line of the **Build Log** for the freshest context.
2. Cross-check git reality lightly: does the plan's branch exist (`git branch --list 'feature/*'`)? Any Phase Log hash not on the branch is a flag (someone forgot step 9, or a rebase ate it).
3. Report as a table:

| Plan | Phases done | Next phase | Branch | Blocked by | Last build-log note |

4. Below the table:
   - **Recommended next action** — one line, honoring plan dependencies (README order) and anything half-done (an in-progress plan beats starting a new one).
   - **Flags** — unchecked predecessor phases, missing branches, Build Log divergence notes that were never resolved, plans untouched >30 days (staleness: re-verify their ground truth before resuming).
   - **Deployment ready** — any plan with all phases checked whose Deployment section hasn't been executed (ask Bryan; check the Build Log for a deploy note).

Keep the whole output under ~30 lines. If Bryan replies "go", hand off to `/implement-phase` with the recommended plan + phase.
