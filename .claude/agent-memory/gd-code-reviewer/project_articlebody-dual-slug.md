---
name: articlebody-dual-slug
description: Plan 10.2 ArticleBody H2 anchor slug — dual-path divergence RESOLVED in-phase via shared headingSlug(); keep the single source, don't reintroduce a second slug computation
metadata:
  type: project
---

Plan 10.2 added an "On this page" TOC. The H2 anchor slug must agree between the
sidebar `<a href="#slug">` (from `headings()`) and the `<h2 id="slug">` (from
`style()`) or the anchor silently fails to scroll.

**Status: RESOLVED in the same phase** (flagged as a WARN during review, fixed
before commit). Both paths now go through one private helper,
`ArticleBody::headingSlug($innerHtml) = Str::slug(strip_tags($innerHtml))`, over
the SAME CommonMark rendering: `headings()` renders each `## …` block via the
shared `converter()` and reduces the `<h2>` inner HTML; `style()` reduces the
`<h2>` inner it just rendered. So they converge **by construction**, not just for
plain headings — a heading with a markdown link/code/emphasis slugs identically
on both sides (link text only, URL dropped). Locked by
`test_heading_with_inline_markdown_anchors_to_the_same_slug`.

**How to apply:** the drift hazard is gone as long as the single source holds.
On any future edit to heading extraction, slug logic, or `style()`'s `<h2>`
markup, verify BOTH sites still call `headingSlug()` over the rendered inner HTML
— do NOT reintroduce a second slug computation (e.g. slugging raw markdown text
in `headings()`), which is what originally diverged. Same shared-source
discipline as [[priceintel-verdict-duplication]].
