# /drop-write — Daily Drop Step 2: Write the Post for One Product

## Context
GadgetDrop (gadgetdrop.tech) is an Amazon affiliate site for tech and gadgets. This is **step 2 of the daily content pipeline**. You write ONE publish-ready, research-based post for ONE researched product and save it as plain structured markdown to `daily-drop/product-N.md`. Do NOT write any JSON; the `/drop-assemble` step converts these files with a script. This command needs no prior conversation context. Keep chat output to short status lines; all content goes to the file.

> **One post per product. One real byline.** The old four-persona system (Maya/Ken/Elizabeth/Sam) is retired — it produced near-duplicate posts under fictional names, which is exactly what got the site flagged for low-value content. Every post is now written under the site's single real author and must be honest about being research-based.

## Arguments
`$ARGUMENTS` is `N` (1 or 2): write the post for product N from research.md. **Overwrite** `daily-drop/product-N.md`.
With no argument, write product 1 (the day's primary pick). Product 2 is the backup — only write it when product 1 turned out to be a dud (listing gone, wrong ASIN, price way off); in that case skip product 1 entirely, the day still ships one review.

Editorial and AdSense rules for every type live in `CONTENT-GUIDELINES.md` (§ Reviews); the essentials are inlined below so this file works standalone.

## Preflight
Read `daily-drop/research.md`.
- If it is missing, tell the user to run `/drop-research` first and stop.
- If its `DATE:` is not today, warn the user it is stale and ask whether to continue anyway. Stop and wait.
- Find the `===PRODUCT N===` block for your product number. Use its NAME, ASIN, PRICE, ANGLE, KEYWORD, CATEGORY, TAGS, and ALTERNATIVES. Do not re-research; only search the web if you need a specific fact (real specs, current price, a pattern in owner reviews) to avoid fabricating.

## Voice
Write as **Bryan McNeil** — the site's one real editor. Plain-English, direct, a little dry. Explains specs in real-world terms ("the 40ms latency means it feels instant"). Uses dollar amounts and concrete numbers over adjectives. Opinionated: "you'll love this if… / skip it if…" framing. Values the reader's time.

## Honesty rules (non-negotiable — these exist because the site was denied AdSense for unverifiable claims)
- **Never claim first-hand testing.** GadgetDrop reviews are research-based, per `/how-we-review`. Banned framings: "we tested", "I tested", "our testing", "our measurements", "we measured", "in our lab", "hands-on test", "we benchmarked", "after testing", "we've been using", "in my/our testing", "independent testing", "reviewer measurements".
- **Attribute instead.** Correct framings: "verified-purchase owners consistently report…", "on paper, the spec sheet claims X; owner feedback puts it closer to Y", "professional testers measured…", "the manufacturer rates it at…".
- **Never invent price history.** The site tracks real prices and renders its own widget; the body may talk about price positioning qualitatively ("launched at $199, sits at $149 as of this writing") but must never fabricate "was/now" numbers or historic lows.
- Every post names at least one real downside a buyer should know before paying.

## Post structure (each section separated by a markdown `---` rule)

- **Hook paragraph**: relatable problem or surprising fact, never "In this article we will…". No heading.

---

- **What it is**: **Required SEO heading.** Never the generic label "What It Is". Write an H2 containing the product name or focus keyword: e.g. `## What Is the Anker 737 Power Bank?`. Plain-language, no jargon.

---

- **Who it's for**: **Required SEO heading.** Never the generic "Who It's For". An H2 framing the audience around the product: e.g. `## Who Should Buy the Anker 737?`. Specific personas and use cases.

---

- **Key features in context**: 3–5 bullets, benefits-first (these become the Pros). Each bullet ties a spec to a real-world outcome. Heading flexible; prefer keyword-rich phrasing over `## Key Features`.

---

- **Price context**: 1 short paragraph. Where does the price sit for the category ("$149 is mid-pack for flagship-ANC earbuds"), what you're paying for vs. the step-down option, and a pointer that the live tracked price and its history render right on this page ("the price widget above shows where today's number sits against our tracked history"). **Qualitative only — no invented numbers.** Heading flexible: `## What You'll Pay`, or product-specific.

---

- **How it compares**: name 1–2 real alternatives from the ALTERNATIVES field of research.md, one sentence each on when the alternative is the better buy. When an alternative has a GadgetDrop review, link it inline as a **site-relative markdown link**: `[our Nothing Ear (3) review](/posts/nothing-ear-3-should-you-actually-buy-them)`. At least one internal link when ALTERNATIVES provides a slug. Heading: `## {Product} vs. {Alternative}` or `## The Alternatives Worth Considering`.

---

- **Honest take**: one downside or "not for you if…" (this becomes the Con). Sourced honestly (owner-feedback patterns, spec limitations). Heading flexible: `## One Thing to Consider` or product-specific.

---

- **FAQ**: 3 questions a real buyer would Google before purchasing. Format:

  **Q: {question}**
  {2–3 sentence answer}

  Target "People Also Ask" style questions: comparisons, compatibility, "is it worth it", battery life, etc.

---

- **Verdict**: punchy 2-sentence wrap-up with a clear buy / wait / skip lean.

---

- **Closing nudge**: 1–2 sentences, NO link. The product card above the article is the site's single affiliate CTA, so do not put an Amazon link in the body. Example: "If the current price in the card above sits at or below typical, this is an easy yes."

## Writing rules
- Active voice, no filler words ("very", "really", "truly")
- Never start a sentence with "Overall" or "In conclusion"
- Amazon Associates disclosure is added automatically by the site, do NOT include it
- Title: 50–65 characters, compelling, contains the main keyword near the start
- Excerpt: 120–155 characters, includes the keyword, entices the click
- Body: **900–1500 words** (one deeper post instead of four shallow ones)

**Banned patterns, zero exceptions:**
- Em dashes (—) in any form. Use a comma, a period + new sentence, or restructure instead.
- "dive into", "deep dive", "let's dive"
- "game-changer" / "game changer"
- "it's worth noting", "worth noting"
- "seamlessly", "seamless integration"
- "unleash", "unlock your", "elevate your"
- "robust" as a feature adjective
- "cutting-edge" unless quoting the manufacturer verbatim
- "at the end of the day", "in today's world", "in today's fast-paced"
- "look no further"
- Opening a sentence with "Additionally," or "Furthermore,"
- Passive "is designed to" constructions
- Every first-hand-testing phrase in the Honesty rules above

**Naturalness rules:**
- Vary sentence length deliberately: mix short punchy sentences with longer explanatory ones.
- Use contractions throughout: "you'll", "it's", "doesn't", "that's".
- One concrete number or real-world comparison per section beats three vague adjectives.
- If a sentence could appear unchanged in any product review, rewrite it to be specific to this product.

## SEO self-check (before saving)
Score the post 0–100 against this checklist. If it scores below 75, revise the title, excerpt, and headings before saving:
- Title contains focus keyword near the start (50–65 chars)
- Excerpt 120–155 chars, includes keyword
- Focus keyword in first 100 words of body
- At least 2 H2/H3 subheadings contain related keywords
- Body 900–1500 words
- At least one internal link to another GadgetDrop review
- Slug short, lowercase, hyphenated, contains keyword

## Output file format
Save to `daily-drop/product-N.md` with the Write tool. **Exactly one block** (field names are parsed by `bin/daily-drop-build.php`, keep them verbatim; BODY must be the last field):

```
===POST===
AUTHOR: Bryan McNeil
TITLE: {50–65 chars, contains keyword}
EXCERPT: {120–155 chars}
TYPE: article
CATEGORY: {one of: Audio & Home Theater | Smart Home | Computers & Accessories | Gaming | Wearables | Cameras — from research.md, adjust only if clearly wrong}
TAGS: {tag1 | tag2 | tag3 | tag4 | tag5}
ASIN: {B0XXXXXXXXX from research.md}
RATING: {one of 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5 — honest editorial score; everything being 4.5+ destroys credibility}
PROS:
- {benefit 1, 5–10 words}
- {benefit 2}
- {benefit 3}
CONS:
- {drawback 1, 5–10 words}
SEO_SCORE: {your checklist score, 0–100}
META_TITLE: {≤70 chars, keyword-first}
META_DESCRIPTION: {120–155 chars, includes keyword}
FOCUS_KEYWORD: {primary seo keyword}
TARGET_QUERY: {the exact query this post should win — copy TARGET_QUERY from research.md (falls back to the focus keyword). Optional but recommended: it makes the post's intent measurable in Search Console.}
SLUG: {seo-friendly-hyphenated-slug}
BODY:
{full markdown body: ## headings, --- dividers, **bold**, internal /posts/ links. Plain markdown, no escaping, no code fences, no Amazon links. Runs until end of file.}
```

## Done
Print to chat, nothing more:
```
✅ Review written — {product name}
Saved to daily-drop/product-N.md
Next: {per today's cadence — /drop-tip (Tue/Sat), /drop-news (Mon/Thu), otherwise /drop-assemble}
```
