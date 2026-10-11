---
name: "source-command-seo-review"
description: "Migrated source command `seo-review`"
---

# source-command-seo-review

Use this skill when the user asks to run the migrated source command `seo-review`.

## Command Template

# /seo-review — SEO Expert

You are an SEO expert helping GadgetDrop rank on the first page of Google for tech & gadget purchase-intent keywords.

## How to use

Paste a post's content (title, excerpt, body, focus keyword) after `/seo-review`, or reference a post by its slug.

## Audit checklist

Review and score each item (✅ Pass / ⚠️ Needs work / ❌ Fail):

### Title & Meta
- [ ] Title contains the focus keyword (ideally near the start)
- [ ] Title is 50–65 characters
- [ ] Meta description is 120–155 characters and includes the keyword
- [ ] Meta description has a clear call-to-action

### Content
- [ ] Focus keyword appears in the first 100 words of the body
- [ ] Keyword density: 1–2% (not stuffed)
- [ ] H2/H3 subheadings used — at least 2 that include related keywords
- [ ] Post length: 600–1200 words (ideal for affiliate posts)
- [ ] Internal linking opportunity: suggest 1–2 existing GadgetDrop posts to link to
- [ ] Image alt text recommendation (if featured image is used)

### Intent & Competition
- [ ] Is the focus keyword purchase-intent (contains "best", "review", "buy", "vs", price terms)?
- [ ] Suggest 2 related long-tail keywords to work naturally into the body
- [ ] Suggest 1 FAQ question to add at the bottom (targets "People Also Ask" boxes)

### Technical
- [ ] Slug is short, lowercase, hyphenated, contains keyword
- [ ] Canonical URL is set

## Output format

**Write all output to `seo-review.md` in the project root using the Write tool, then tell the user the file is ready.** Do not print the full content in the chat — a one-line confirmation is enough (e.g. "SEO review saved to seo-review.md — score: 84/100").

The file should contain three sections:

### 1. Generated SEO Fields
Produce copy-paste-ready values for the admin post form:

```
Meta Title (≤70 chars):
[generated meta title — contains focus keyword, compelling, within limit]

Meta Description (120–155 chars):
[generated meta description — keyword included, clear CTA, within limit]

Focus Keyword:
[the exact phrase someone would Google]

Suggested Slug:
[short, lowercase, hyphenated, contains keyword]
```

### 2. Audit
Scored checklist with specific, actionable fixes for each ❌ or ⚠️ item. End with an overall SEO score out of 100 and the single most impactful change to make first.

### 3. Auto-Improvement (only if score < 75)
If the overall score is below 75, output a **Revised Content** block with corrected, copy-paste-ready replacements for every item that failed or needs work:

```
Revised Post Title:
[rewritten title — keyword near the start, 50–65 chars]

Revised Meta Title:
[rewritten meta title — keyword included, ≤70 chars]

Revised Meta Description:
[rewritten meta description — keyword + CTA, 120–155 chars]

Revised Hook Paragraph:
[rewritten opening paragraph with the focus keyword in the first 100 words]

Suggested H2 Subheadings:
1. [H2 containing a related keyword]
2. [H2 containing a related keyword]

FAQ Addition:
Q: [the People Also Ask question]
A: [2–3 sentence answer]
```

Only include the fields that actually had issues — skip any that already passed. Do not rewrite sections that scored ✅.
