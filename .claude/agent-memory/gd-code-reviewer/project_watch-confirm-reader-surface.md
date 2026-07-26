---
name: watch-confirm-reader-surface
description: watch-confirm.blade.php is a reader-facing watch surface (email-link landing page) that lives outside mail/ + signup — easy to miss in copy/voice passes; keep it in the reader-facing set
metadata:
  type: project
---

`resources/views/public/watch-confirm.blade.php` is the verify/unsubscribe **landing page** readers reach directly from the links in the watch emails (`PriceWatchController` comments: "reached only from email links — never indexable"). It is fully reader-facing but lives outside the mail-view folder and the signup widget, so it is easy to forget in any watch copy/voice pass.

**Why:** Phase 10.5 (email + watch voice) purged em-dashes from the six enumerated files plus Bryan's disclosed extension to the mailable PHP strings. This page was outside that literal scope (design-decision line 32 enumerates only "three mails + the signup widget"), and the review WARN flagged that it still carried two reader-facing em-dashes — inconsistent with the emails that link to it. **Resolved in-phase:** Bryan's standing "everything the reader sees" decision applied, so the two em-dashes were folded into the 10.5 commit ("No more emails about it, ever.", "you'll get one email. That's the whole deal.") and covered by `PriceWatchFlowTest` assertions on both the verify and unsubscribe states. Do not re-flag it as dirty.

**How to apply:** In any future watch/mail voice or em-dash review, include `watch-confirm.blade.php` in the reader-facing surface set alongside `resources/views/mail/*` and `livewire/price-watch-signup.blade.php` — not because it is currently dirty (it is clean as of 10.5) but because its location makes it easy to skip. Confirmed-safe pattern from the same review: em-dashes remaining in **docblocks/inline comments** across the watch PHP files (`PriceWatchSignup.php`, `PriceWatchController.php`, `CheckPriceWatches.php`, `config/watch.php`, mailable docblocks) are deliberately developer-facing — do not flag them. See [[design-refresh-plan-status]].
