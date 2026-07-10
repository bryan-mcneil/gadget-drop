---
name: dusk-ci-workflow
description: Dusk CI workflow (plan 00 Phase 0.3) patterns confirmed safe — don't re-flag backgrounded serve or missing serve-readiness wait
metadata:
  type: project
---

`.github/workflows/dusk.yml` (plan 00-ci-and-testing-foundation §0.3) — weekly `schedule` cron `0 6 * * 1` + `workflow_dispatch`, main-only by GitHub semantics.

**Confirmed-safe patterns (do NOT flag as findings):**
- `php artisan serve --no-reload &` in its own step, then `php artisan dusk` in the next step. Background process persists across GitHub Actions steps — this is the *official Laravel Dusk CI pattern* (docs/plans/00 §0.3, mirrors Laravel docs). No `disown` needed.
- No explicit serve-readiness wait between serve and dusk. Mitigated by step-boundary overhead; official docs omit it too. At most an optional NIT, not a WARN.
- `.env` IS the dusk env on CI (`cp .env.dusk.example .env`). Dusk only swaps `.env`↔`.env.dusk.local` when the latter exists; on CI it doesn't, so `.env` is used directly. Correct by design.
- `npm run build` is REQUIRED before Dusk (real pages use `@vite`; no manifest → 500). Its presence is correct, not scope creep.
- `database/dusk.sqlite` touched by the workflow AND by `DuskTestCase::refreshApplication()` — redundant but harmless.

**Why:** these looked flaggable on first read (backgrounded process across steps, no health check) but are validated/official. **How to apply:** APPROVE-level; the only real proof is a `workflow_dispatch` run on main post-merge (workflow_dispatch UI only surfaces workflows on the default branch), which is a known/accepted limitation, not a blocker.

Related: [[project_dusk-sqlite-truncation]] (Dusk uses DatabaseTruncation, not DatabaseMigrations).
