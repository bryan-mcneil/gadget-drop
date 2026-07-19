---
name: dusk-clicklink-navigation-race
description: Dusk clickLink is a JS synthetic click that does NOT block for full-page navigation; assertPathIs/assertFragmentIs/assertVisible after it race the load — require waitForLocation
metadata:
  type: project
---

In this repo's Dusk version, `Browser::clickLink()` runs `executeScript("jQuery.find(...)[0].click();")` — a synthetic JS click. It returns immediately after dispatching the click and does NOT wait for the resulting native full-page navigation to complete.

**Why:** `assertPathIs`, `assertFragmentIs`, `assertVisible`, and `assertSee` do NOT auto-wait (only `waitFor*` variants do). So `clickLink(...)->assertPathIs('/dest')` races the navigation — it can evaluate against the pre-nav page or a partially loaded page. Passes on a fast local run, flakes in CI. First seen: plan 01 §1.5 `tests/Browser/VerdictSurfacesTest.php` test_the_methodology_link_lands_on_the_deal_verdicts_anchor (the "How we call deals" plain #deal-verdicts link). The repo's CI gate is convention-only (private GitHub Free), so flaky browser tests are especially corrosive.

**How to apply:** WARN any new Dusk test that does `clickLink(...)` (or any JS-click causing a native/full-page nav) followed directly by path/fragment/visibility/see asserts with no intervening wait. Concrete fix: insert `->waitForLocation('/dest')` (or `waitForReload`) between the click and the asserts. Note: a `visit()` DOES block for load (page-load strategy 'normal'), so plain `visit()->assertSee()` needs no wait — this is specifically the clickLink/JS-click→native-nav case. Livewire SPA transitions use `waitForLivewire`/`waitForText` instead (see WorthItVoteTest). Related: [[wire-navigate-fragment-exception]] (these fragment links are plain links by design, which is exactly why the nav is native, not a wire:navigate swap).
