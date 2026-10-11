---
name: deals-query-count-guard
description: /deals N+1 guard tests use a loose fixed query ceiling by plan design — WARN not BLOCKER on sensitivity
metadata:
  type: project
---

The `/deals` feed N+1 guard tests assert a loose fixed query ceiling (e.g. `assertLessThanOrEqual(25, $queries)` in `WorthItVoteSurfacesTest::test_deals_vote_counting_does_not_add_per_post_queries`) rather than an exact count.

**Why:** Plan 02 §Phase 2.3 (and the deals-feed pattern generally) explicitly chose a "fixed sane number" over exact-count assertions, calling `expectsDatabaseQueryCount`/exact counts brittle. So the loose ceiling is intentional, not an oversight.

**How to apply:** Do NOT BLOCK on these guards for using a loose ceiling — it's plan-blessed. DO note as a WARN when the seed size is small (e.g. 3 posts) and the regression it guards (per-post `worthItSummary()` = ~2 COUNT queries/post) would only add a handful of queries — the ceiling can then pass even with the N+1 reintroduced, giving false confidence. Suggested tightening: seed ~10 posts or assert near-exact. Related: [[show-post-is-array]], [[worthit-skip-scope-safe]].
