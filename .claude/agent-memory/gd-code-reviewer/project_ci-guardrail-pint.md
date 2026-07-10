---
name: project-ci-guardrail-pint
description: How to judge the DB-wipe-guardrail auto-BLOCKER rule when a change is a semantics-preserving pint reformat
metadata:
  type: project
---

Plan 00 (CI & testing foundation) Phase 0.1 shipped a `vendor/bin/pint` sweep (commit c69643e) that touched the two DB-wipe guardrail files `tests/bootstrap.php` and `tests/TestCase.php`.

**Why:** CLAUDE.md says any diff touching `tests/bootstrap.php`, `DB::prohibitDestructiveCommands` (AppServiceProvider), or `TestCase::refreshApplication` is an automatic BLOCKER pending Bryan's explicit sign-off. But pint's changes there were purely cosmetic — double-quote→single-quote and concat-operator spacing inside abort *message strings*; the abort *logic* (env/connection/`:memory:` checks, `exit(1)`) was byte-for-byte unchanged.

**How to apply:** When the guardrail-file touch is (a) a provably semantics-preserving formatter change, (b) authored by Bryan himself, and (c) under a plan that pre-authorized the sweep, downgrade from hard BLOCKER to a WARN that names the touch and asks for explicit acknowledgement — don't fail the whole verdict over cosmetic string reformatting. Still ALWAYS diff those files line-by-line and confirm the control-flow/abort predicates are identical before downgrading. If any predicate, string that feeds a predicate, or `exit`/`abort` path changes, it stays a BLOCKER.
