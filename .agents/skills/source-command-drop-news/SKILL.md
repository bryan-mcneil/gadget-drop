---
name: "source-command-drop-news"
description: "Migrated source command `drop-news`"
---

# source-command-drop-news

Use this skill when the user asks to run the migrated source command `drop-news`.

## Command Template

# /drop-news — Write Today's Tech News Post (Mon + Thu)

## Context
GadgetDrop (gadgetdrop.tech) publishes buyer-focused tech news twice a week (see the cadence table in `/daily-drop`). GadgetDrop news is NOT wire-service rewriting: every story is chosen and framed for someone deciding whether to buy something — price cuts, product launches, recalls, discontinued models, spec bumps. **Pick the story the same morning you write it** — freshness is the point. Save as plain structured markdown to `daily-drop/news-1.md`; `/drop-assemble` converts it. No prior conversation context needed. Keep chat output to short status lines.

Editorial and AdSense rules live in `CONTENT-GUIDELINES.md` (§ Tech News); the essentials are inlined below so this file works standalone.

## Step 1 — Pick the story
**Check the SEO brief first.** If `daily-drop/seo-brief.md` exists and is ≤ 3 days old, its "Tip / news angles" section lists queries surging in our own Search Console/Bing data (`rising`) with no dedicated post — a strong steer for which of today's stories to cover if one lines up. Freshness still wins: only follow the brief when a genuinely current, buyer-relevant story matches it. If the brief is missing/stale, use editorial judgment as below.

Search today's tech news (The Verge, Ars Technica, TechRadar, Engadget, 9to5Mac/Google, company newsrooms):

**Filter for:** published in the last ~24–48 hours · directly affects a buying decision · touches one of the 6 site categories · ideally adjacent to products GadgetDrop reviews (cross-link opportunity). Skip: rumors without a credible source, enterprise/B2B, financial/stock stories.

Record the single best source URL — it becomes `SOURCE_URL` (required; the public page renders "Source: {domain}").

## Step 2 — Write it
Voice: **Bryan McNeil** — plain-English, direct, a little dry. Inverted pyramid: the fact first, context second, analysis third.

Structure:
- **Lede**: what happened + why a buyer cares, in the first two sentences. No heading.
- **`## {keyword-rich H2 with the product/company name}`**: the details — numbers, dates, prices, what changed vs. before.
- **What it means for buyers**: original analysis, not summary. Who wins, who should ignore it, what it signals about the category. If GadgetDrop reviews an affected product, link it site-relative (`[our X review](/posts/slug)`).
- **`## Buy or Wait?`**: **required section, exact heading** (the build script hard-errors without it). A clear, committed verdict: buy now / wait for X / skip. Two short paragraphs max. **If the story touches a product line we track a release cycle for** (iPhone, Samsung Galaxy S, Google Pixel, AirPods Pro, iPad, MacBook Air, Sony WH-1000X, Nintendo Switch, GoPro HERO, Kindle Paperwhite), link its live verdict page site-relative once in this section, e.g. `[our buy-or-wait verdict for the iPhone](/buy-or-wait/iphone)`. One link, and only when the line genuinely applies; never invent a slug (the full list is at `/buy-or-wait`).

Rules (same as every post type):
- 600–900 words. Under 600 reads as thin content; earn the length with analysis, never padding.
- **Original analysis, never a wholesale rewrite of the source.** Report the facts in your own words, cite the source, spend most of the words on what it means. Quote at most one short phrase.
- No invented numbers or price history; only figures from the source or the site's own tracked data.
- No first-hand-testing claims, no em dashes, no banned phrases, no "Additionally,"/"Furthermore," openers (full lists in `/drop-write`).
- No Amazon links in the body, no affiliate CTA — news earns trust and freshness signals, not clicks.
- Title 50–65 chars, newsy but specific (a real fact, not clickbait). Excerpt 120–155 chars.

## Output file format
Save to `daily-drop/news-1.md` with the Write tool. **Exactly one block** (field names parsed by `bin/daily-drop-build.php`; BODY must be last; no ASIN/RATING/PROS/CONS — those are review-only):

```
===POST===
AUTHOR: Bryan McNeil
TITLE: {50–65 chars, specific fact near the start}
EXCERPT: {120–155 chars}
TYPE: tech_news
CATEGORY: {one of: Audio & Home Theater | Smart Home | Computers & Accessories | Gaming | Wearables | Cameras — never invent a category}
TAGS: {tag1 | tag2 | tag3 | tag4}
SOURCE_URL: {https://... the story's primary source — required}
SEO_SCORE: {0–100 self-check}
META_TITLE: {≤70 chars, keyword-first}
META_DESCRIPTION: {120–155 chars, includes keyword}
FOCUS_KEYWORD: {primary search phrase}
TARGET_QUERY: {the exact query this story should win — the brief candidate's query if it came from the brief, else the focus keyword. Optional but recommended.}
SLUG: {seo-friendly-hyphenated-slug}
BODY:
{full markdown body including the required ## Buy or Wait? section. Runs until end of file.}
```

## Done
Print to chat, nothing more:
```
✅ News post written — {title}
Saved to daily-drop/news-1.md
Next: run /drop-assemble
```
