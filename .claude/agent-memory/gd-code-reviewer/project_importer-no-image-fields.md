---
name: importer-no-image-fields
description: DailyDropImporterService (posts:import) writes NO image fields — plan steps about importer image/caption passthrough are moot, not a gap
metadata:
  type: project
---

`App\Services\DailyDropImporterService::importAll()` (behind `posts:import` / the `/admin/daily-drop` Import page) creates posts with only: type, title, slug, excerpt, body, status(draft), user_id, source_url, rating, pros, cons, + category/tag/product sync + SEO meta. It sets **no image fields at all** — `image_1/2/3`, `*_fit`, `*_caption`, `hero_image`, `featured_image` are all added manually in `/admin` after import.

**Why:** the daily-drop pipeline delivers text; images are a human editorial step (CLAUDE.md typical workflow: "Review the drafts, add images, publish"). The build script even warns on in-body Amazon links but never handles images.

**How to apply:** when a plan step says "`posts:import` passes X image field through" (e.g. Plan 10.1 step 6, captions), that step is **moot, not missing** — no importer change is the correct implementation. Do NOT flag the absent importer edit as an incomplete-scope BLOCKER; confirm the deviation was flagged (it was) and pass it. Verify against the `Post::create([...])` array in DailyDropImporterService if a future phase claims importer image handling.
