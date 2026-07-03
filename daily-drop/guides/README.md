# Deep-dive Guides (AdSense content layer)

Five education-first guides written to show Google a real editorial publication,
not an affiliate bridge. They are intentionally **not** single-product pages — each
helps the reader make a decision and references products only as examples.

## How to publish each one
1. In `/admin`, create a new Post.
2. Copy the **Admin fields** from the guide file into the matching inputs:
   - **Title**, **Slug**, **Excerpt** (used as the meta description).
   - **Type:** `article`.
   - **Author:** your real author account (the one created by `php artisan authors:consolidate`).
   - **Category:** `Guides` — create this category once (Categories → New → name "Guides",
     slug `guides`). The header "Guides" nav link appears automatically once it exists
     and has at least one published post.
3. Paste everything under **Body** into the post body field (it's Markdown).
4. Add a featured image (see the suggestion in each file), then **Publish**.
5. Optionally fill the SEO panel; the excerpt already works as the meta description.

## Notes
- Internal links point at existing reviews by slug — keep them so the guides interlink
  with the rest of the site (good for SEO and for looking like a real publication).
- Affiliate links are deliberately sparse. If you add an Amazon link, route it through
  the product (`/out/{product}`) like everywhere else — never a raw Amazon URL in the body.
- Voice: plain-English, practical, honest. "Who it's for / when to skip." No spec-sheet dumping.

## The five guides
1. `guide-1-minimalist-tech-desk.md`
2. `guide-2-wireless-earbuds-buying-framework.md`
3. `guide-3-smart-speaker-privacy.md`
4. `guide-4-smartwatch-battery-life.md`
5. `guide-5-budget-vs-premium-tech.md`
