---
name: public-vs-admin-css-bundle
description: Which built CSS bundle is the PUBLIC site vs admin — grep the manifest-mapped file, not `ls -t`, when verifying Tailwind purge
metadata:
  type: reference
---

When verifying that new Blade Tailwind classes survived purge (build-verification greps),
target the CSS bundle the **manifest** maps to `resources/css/app.css` / `resources/js/app.js`,
NOT whatever `ls -t public/build/assets/app-*.css` returns (mtime picks the wrong one).

As of Plan 10.3 (2026-07-24) the manifest maps:
- `resources/css/app.css` + `resources/js/app.js` (PUBLIC site) → **`app-<hash>.css`** whose
  hash matches the app.js/app.css entry (e.g. `app-DdTbOYCr.css`). This is where post-card,
  home, category, verdict-badge utilities live.
- `resources/js/app.jsx` + `vendor-cropper` (ADMIN/React) → a **different** `app-<hash>.css`
  (e.g. `app-_JpMZc7H.css`). The design-refresh plan-status memory calls this one "orphaned
  pending 10.6 cleanup" — imprecise: it is the admin CSS and is still manifest-referenced.

**How to apply:** to find the right file, parse `public/build/manifest.json` — the `css:[...]`
array under the `resources/js/app.js` entry is the public bundle. Grep THAT for the new
classes (CSS-escaped: `min-h-\[176px\]`, `motion-reduce\:transition-none`, `w-2\/5`). A miss
there = real purge failure; a miss in the admin bundle is expected for public-only classes.
Hashes rotate every build — re-read the manifest each time.

**Two cheap proofs for any diff that carries `public/build` (learned 2026-07-26, card-zoom bugfix):**
1. *What the build actually added:* set-diff the CSS rules, old vs new —
   `git show HEAD:public/build/assets/app-OLD.css` vs the new file, split on `}`, compare sets.
   A Blade-only phase should yield a tiny, explainable delta (that review: exactly
   `+ .-inset-px{inset:-1px}`, zero removed → no purge casualties). Zero-removed is the real
   purge check; it beats grepping for classes one at a time.
2. *That the JS churn is non-semantic:* read old (via `git show HEAD:`) and new chunk pairs,
   normalise `-[A-Za-z0-9_-]{8}\.(js|css)` → `-HASH.$1`, compare. Rolldown rewrites importer
   chunks when any imported chunk rehashes, so the whole admin bundle renames while being
   byte-identical after normalisation. Verify `app-<hash>.js` (public) / `tools-<hash>.js` are
   *absent* from the changed set — a public-JS change in a Blade-only diff is a real finding.

Corollary: "these utilities already exist in the built CSS, so no `npm run build` needed" must be
checked **per class against the pre-build bundle**. In that bugfix `-inset-px` was genuinely new
(the tag.blade twin would have shipped broken without the rebuilt CSS) even though every other
class in the diff was already present.
