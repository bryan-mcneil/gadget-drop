# Plan 00 — CI & Testing Foundation

**Goal:** every PR (including the cloud agent's nightly `drop/YYYY-MM-DD` PRs) is automatically gated by the test suite and a build check; the repo gains a browser-level e2e layer (Laravel Dusk) that later plans build on.
**Size:** S · **Branch:** `feature/ci-foundation` · **Depends on:** nothing
**Non-goals:** deployment automation (deploys stay manual via `bin/deploy.sh`), coverage reporting, Pest migration.

## Phase Log

- [x] Phase 0.1 — GitHub Actions PR gate (commit: 4bd2c62; pint pre-commit: c69643e)
- [ ] Phase 0.2 — Laravel Dusk local setup + smoke test (commit: )
- [ ] Phase 0.3 — Weekly/on-demand Dusk CI job (commit: )
- [ ] Phase 0.4 — Branch protection + docs (commit: )

## Design decisions

- **sqlite `:memory:` on CI** exactly as locally — `phpunit.xml` already forces it; CI adds nothing DB-wise. Never run `php artisan optimize`/`config:cache` in workflows (same footgun as local — see CLAUDE.md DB-wipe guardrails).
- **Dusk uses a file sqlite DB (`database/dusk.sqlite`) with `APP_ENV=testing`** — never MySQL. This keeps all three DB-wipe guardrail layers satisfied: destructive commands stay on env `testing`, and the real `gadget_drop` MySQL DB is untouchable from browser tests. Dusk tests use `DatabaseMigrations` against that file only.
- **Dusk serves via `php artisan serve` (127.0.0.1:8000)**, not Herd — deterministic across local Windows and CI Linux.
- **PR CI stays fast (< ~4 min): tests + build + pint.** Dusk runs weekly on `main` + on demand, not per PR (ChromeDriver spin-up is slow and the cloud-agent PRs are content-only).

---

## Phase 0.1 — GitHub Actions PR gate

**Scope:** `.github/workflows/ci.yml` — jobs `tests`, `assets`, `style` on `pull_request` + `push` to `main`.

Steps:
1. `tests` job: ubuntu-latest → `shivammathur/setup-php@v2` (PHP 8.4, extensions: `mbstring, sqlite3, pdo_sqlite, gd, intl, zip`) → cache composer → `composer install --no-interaction --prefer-dist` (CI is Linux; the Windows `--prefer-source` note doesn't apply) → `cp .env.example .env` + `php artisan key:generate` → `php artisan test`. Do NOT add a config-cache step.
2. `assets` job: setup-node 22 + npm cache → `npm ci --legacy-peer-deps` → `npm run build`. Fails the PR if Vite/Tailwind breaks.
3. `style` job: `vendor/bin/pint --test`. If the current tree fails pint wholesale, run `vendor/bin/pint` once as the first commit of this phase (`chore(ci): pint the tree before enforcing style`) so the gate starts green — flag the diff size to Bryan before committing it.
4. Concurrency group per ref so superseded pushes cancel.

Tests: the workflow itself (green run on the PR that introduces it). Add a deliberate failing-then-fixed commit locally only if debugging is needed — don't leave red runs on main.

Commit: `ci(ci): add PR gate — phpunit, vite build, pint`

Review checklist: phpunit env untouched (`phpunit.xml` not modified); no secrets in workflow; `--legacy-peer-deps` present (Vite 8 peer mismatch); triggers cover `drop/*` PRs (default `pull_request` does).

## Phase 0.2 — Laravel Dusk local setup + smoke test

**Scope:** Dusk installed, guarded, one smoke test proving the harness.

Steps:
1. `composer require laravel/dusk --dev --prefer-source` (Windows locking note in CLAUDE.md). Check the installed major supports Laravel 13 before proceeding; if not, STOP and report.
2. `php artisan dusk:install`. Pin ChromeDriver: `php artisan dusk:chrome-driver --detect`.
3. Create `.env.dusk.local`: `APP_ENV=testing`, `APP_URL=http://127.0.0.1:8000`, `DB_CONNECTION=sqlite`, `DB_DATABASE=database/dusk.sqlite`, `MAIL_MAILER=array`, `CACHE_STORE=file`, `SESSION_DRIVER=file`, `ADSENSE_ENABLED=false`. Add `database/dusk.sqlite` + `tests/Browser/screenshots` + `tests/Browser/console` to `.gitignore`.
4. **Guardrail (do not skip):** in `tests/DuskTestCase.php`, before the suite runs, hard-abort unless `app()->environment('testing')` AND the DB connection is sqlite AND the database path ends with `dusk.sqlite` — mirroring `tests/TestCase.php::refreshApplication()`'s paranoia. A Dusk run must be physically unable to touch MySQL.
5. Smoke test `tests/Browser/SmokeTest.php`: home page renders the H1, nav is visible, Join the Drop email input exists. Seed minimal data with factories in the test (file DB starts empty).
6. Document the two-terminal local flow at the top of the test: `php artisan serve --env=dusk.local` … actually: run `php artisan dusk` (Dusk boots the serve process itself via `--env`); verify and write down whichever invocation works on Windows in the plan's Build Log.

Tests: `php artisan dusk` green locally; `php artisan test` still green (Dusk suite must not run inside phpunit's default testsuites — confirm `phpunit.xml` untouched).

Commit: `test(ci): install Dusk with sqlite-file guardrails + homepage smoke test`

Review checklist: DuskTestCase abort logic present and tested by intentionally mis-setting env once; `.env.dusk.local` not committed (it contains nothing secret, but keep parity with `.env` handling — commit a `.env.dusk.example` instead); no `RefreshDatabase` in browser tests (use `DatabaseMigrations`).

## Phase 0.3 — Weekly/on-demand Dusk CI job

**Scope:** `.github/workflows/dusk.yml` — `workflow_dispatch` + `schedule` (Mondays 06:00 UTC), `main` only.

Steps: ubuntu-latest → PHP + Chrome (`browser-actions/setup-chrome`) → composer + npm install → `npm run build` → create `database/dusk.sqlite` → `php artisan dusk:chrome-driver --detect` → `php artisan serve &` with dusk env → `php artisan dusk`. Upload `tests/Browser/screenshots` as artifact on failure.

Commit: `ci(ci): weekly Dusk browser run on main`

Review checklist: schedule respects free-runner budget (1 run/week); artifacts only on failure; job cannot run against anything but the sqlite file.

## Phase 0.4 — Branch protection + docs

**Scope:** repo settings (manual, Bryan) + docs.

Steps:
1. Bryan (manual, guided): GitHub → branch protection on `main`: require `tests`, `assets`, `style` checks; allow admins to bypass (solo-operator escape hatch).
2. Update `docs/OPERATIONS.md`: `/morning` now checks the PR's CI status before merging (CI green replaces nothing — the dry-run import stays — but a red PR is an early stop signal).
3. Note in `daily-drop/CLOUD-AGENT.md` that PRs must pass CI; content-only PRs shouldn't trip it, so a red run means the agent touched code it shouldn't have.

Commit: `docs(ci): wire CI into morning + cloud-agent runbooks`

## Testing summary

Unit/feature: none new beyond the smoke harness. E2E: SmokeTest. Meta-test: first real PR through the gate.

## Deployment

No server-side changes — this plan never touches production. Standard template steps 1–2 only.

## Maintenance

- ChromeDriver drifts from installed Chrome: `php artisan dusk:chrome-driver --detect` when Dusk starts failing with session errors (add to `/gd-health`).
- Bump workflow PHP version in lockstep with production PHP upgrades (currently 8.4).
- Watch CI minutes usage monthly (free tier); Dusk weekly cadence is the lever.

## Risks

- **Dusk on Windows quirks** (process spawning, path separators): if `php artisan dusk` fights Herd/Windows, fall back to WSL for local browser runs and lean on the CI job — note the outcome in the Build Log rather than burning a day.
- **Pint big-bang diff** (Phase 0.1 step 3) may create merge friction with the open `feature/social-pipeline` branch — coordinate before committing it.

## Build Log

(append one line per phase: date · what happened · surprises)

- 2026-07-09 · Phase 0.1 · Branch `feature/ci-foundation`; pint pre-commit hit 120 files (contingency applied, suite 262 green after); repo had NO `.env.example` — created a sanitized one so CI's `cp .env.example .env` works. Surprise: `SearchBriefCommandTest` `@unlink`s the real tracked `daily-drop/seo-brief.md` on every full-suite run (working-tree pollution — fix as a test-hygiene follow-up).
- 2026-07-09 · Phase 0.1 CI shakedown · First run: `assets` ✅, `style` ❌ (interleaved `Redirects` commit reverted 2 pint concat fixes — re-pinted), `tests` ❌ (`SocialPublishCommandTest` reads `SOCIAL_*_MODE` from `.env` — phpunit.xml doesn't pin it; `.env.example` now ships `manual`, matching the config default). Test-hygiene follow-up: pin the social mode vars in phpunit.xml for hermeticity (out of scope here — phpunit.xml untouched per phase checklist).
