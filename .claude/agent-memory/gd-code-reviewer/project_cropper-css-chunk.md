---
name: cropper-css-chunk
description: public/build/assets/app-_JpMZc7H.css is the _vendor-cropper chunk's CSS referenced in manifest.json — NOT orphaned; don't re-flag deleting it
metadata:
  type: project
---

`public/build/assets/app-_JpMZc7H.css` is **referenced by `manifest.json`** (the `_vendor-cropper` chunk's `css` array, ~lines 82 and 444) — it carries the cropperjs v1 styles for the image-editor tool. Deleting it breaks the cropper.

**Why:** An earlier (pre-10.6) review flagged it as an "orphaned bundle not in the manifest" and deferred the cleanup to 10.6. By 10.6 it was manifest-referenced (rebuild churn re-linked it), so that nit is stale.

**How to apply:** Don't re-raise "delete the orphaned app-_JpMZc7H.css" — confirm against `manifest.json` first (a hashed `app-*.css` that isn't the public `resources/js/app.js` entry css is usually a vendor chunk's css, not dead). The public Tailwind bundle for purge checks is the one mapped from `resources/js/app.js` (see [[public-vs-admin-css-bundle]]); the cropper css is a separate chunk.
