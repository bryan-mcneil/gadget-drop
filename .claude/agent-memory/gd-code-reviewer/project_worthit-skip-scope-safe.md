---
name: worthit-skip-scope-safe
description: WorthItVote model's scopeSkip() does NOT collide with query-builder skip()/offset — confirmed safe, don't re-flag
metadata:
  type: project
---

`WorthItVote` (Plan 02 Worth-It Voting) defines local scopes `scopeWorth()` and `scopeSkip()`. A `skip()` scope name looks like it would shadow the base query builder's `skip()` (offset alias).

**Why safe:** Eloquent\Builder::__call resolves named scopes (`method_exists($model, 'scope'.ucfirst($method))`) BEFORE forwarding to the query builder, so `->skip()` on this model/relation always means the choice='skip' scope, never offset. Confirmed by `WorthItVoteTest::test_summary_hides_percentage_below_the_gate` (skip()->count() returns the skip tally). The name is also plan-mandated (Phase 2.1 step 2).

**How to apply:** Do not flag the `skip()` scope as a collision in this or later Worth-It phases. The only residual footgun would be someone calling `WorthItVote::skip($n)` expecting SQL offset — irrelevant for this model.
