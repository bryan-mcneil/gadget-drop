---
name: dusk-sqlite-truncation
description: Why Dusk browser tests use DatabaseTruncation (not DatabaseMigrations) — sqlite down() bug; confirmed-safe pattern
metadata:
  type: project
---

Dusk (plan 00 Phase 0.2) browser tests use `DatabaseTruncation`, NOT the plan-specified `DatabaseMigrations`.

**Why:** `DatabaseMigrations` runs `migrate:rollback` in per-test teardown, which hits the broken `down()` of migration `2026_06_01_000001_add_share_code_to_posts_table` — it drops the `share_code` column without first dropping its unique index (fine on MySQL, fatal on sqlite). `DatabaseTruncation` runs `migrate:fresh` ONCE (guarded by `RefreshDatabaseState::$migrated`, so `down()` never fires) then truncates between tests. It also avoids `RefreshDatabase`'s transactions, which would hide seeded rows from the separate `php artisan serve` process that Dusk drives.

**How to apply:** This divergence is sound and flagged — do not flag it as a blocker in future Dusk reviews. When reviewing new browser tests, expect `DatabaseTruncation` and treat the plan's "use DatabaseMigrations" text as superseded. The migration `down()` bug is a separate, tracked adjacent-bug follow-up (not fixed in Phase 0.2). If a future phase claims to fix it, verify the unique index on `share_code` is dropped before the column.

The Dusk DB-wipe guard lives in `tests/DuskTestCase.php::refreshApplication()` — mirrors [[project_ci-guardrail-pint]]'s concern: aborts unless env=testing + default=sqlite + database path ends `dusk.sqlite`, before `setUpTraits()` (i.e. before `migrate:fresh` can fire). phpunit.xml testsuites remain Unit+Feature only, so `tests/Browser` cannot leak into `php artisan test`.
