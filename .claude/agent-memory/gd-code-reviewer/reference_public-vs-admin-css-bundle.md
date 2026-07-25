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
