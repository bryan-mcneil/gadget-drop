---
name: worthit-dusk-thanks-not-pct
description: WorthItVote Dusk test asserts "Thanks for voting!" not a percentage — correct because the ≥5 honesty gate hides pct at 1-2 votes; don't flag as a coverage gap
metadata:
  type: project
---

Phase 2.4 (`tests/Browser/WorthItVoteTest.php`) asserts the result-state header `Thanks for voting!` after a vote, NOT a rendered percentage.

**Why correct:** the WorthItVote honesty gate (`summarize()`, `MIN_VOTES_FOR_PCT = 5`, mirrors PriceIntel) returns `pct = null` below 5 votes, so the view shows the "Early votes — you're one of the first N" line, never a percentage, at 1-2 votes. A Dusk assertion on a percentage would be impossible without pre-seeding ≥5 distinct-hash votes. The percentage-render path is already covered in Feature tests (Phase 2.2: "summary line renders at ≥5 votes"). So the E2E pass correctly limits itself to the browser-only value: the no-reload state swap, session-hash persistence across `->refresh()`, and independent second-browser voting.

**How to apply:** Do NOT WARN that the Dusk test "never asserts a percentage." That is by design, not a gap. If a future phase wants the E2E percentage-render covered, it must seed ≥5 votes with distinct `voter_hash` values first. Related: [[project_show-post-is-array]] (why the component takes `postId` int, mounted via `@livewire('worth-it-vote', ['postId' => $post['id']])`), [[project_dusk-sqlite-truncation]] (DatabaseTruncation + shared sqlite file is why the post-browse `WorthItVote::count()` DB assertions can read the serve process's writes).
