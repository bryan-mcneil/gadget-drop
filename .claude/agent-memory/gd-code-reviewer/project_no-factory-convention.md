---
name: no-factory-convention
description: Repo has no model-factory convention (only UserFactory); plan "factory" scope lines are satisfied by makeX() create-helpers
metadata:
  type: project
---

This repo has **no model-factory convention**. `database/factories/` contains only the Breeze `UserFactory`. Every feature test builds models via private `makeX(array $overrides)` helpers that call `Model::create()` (e.g. `PriceSnapshotTest::makeProduct()`, `PriceWatchTest::makeProduct()/makeWatch()`).

**Why:** A lone new `PriceWatchFactory` would need `Product::factory()` to compose with — which doesn't exist — so it would either be useless or force inventing a Product factory (scope creep into an untested convention).

**How to apply:** When a plan's phase scope line says "factory" (they do so generically), treat it as satisfied by a repo-consistent `makeX()` create-helper mirrored from the nearest sibling test. Do NOT flag the absence of a real Factory as a plan-conformance miss, and do NOT ask the implementer to introduce `Model::factory()`. Confirmed acceptable at Plan 03 §3.1.
