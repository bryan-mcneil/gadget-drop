# /drop-write — Daily Drop Step 2: Write Posts for One Product

## Context
GadgetDrop (gadgetdrop.tech) is an Amazon affiliate site for tech and gadgets. This is **step 2 of the daily content pipeline**. You write publish-ready posts for ONE researched product and save them as plain structured markdown to `daily-drop/product-N.md`. Do NOT write any JSON; the `/drop-assemble` step converts these files with a script. This command needs no prior conversation context. Keep chat output to short status lines; all content goes to the file.

## Arguments
`$ARGUMENTS` is one of:
- `N` (1 to 4): write all 4 author voices for product N. **Overwrite** `daily-drop/product-N.md`.
- `N VoiceName` (e.g. `2 Ken`): write only that author's post and **append** its `===POST===` block to `daily-drop/product-N.md` (create the file if missing; never duplicate an author already in the file, replace that block instead).

If no argument is given, look at which `daily-drop/product-*.md` files exist and write the next missing product.

## Preflight
Read `daily-drop/research.md`.
- If it is missing, tell the user to run `/drop-research` first and stop.
- If its `DATE:` is not today, warn the user it is stale and ask whether to continue anyway. Stop and wait.
- Find the `===PRODUCT N===` block for your product number. Use its NAME, ASIN, PRICE, ANGLE, KEYWORD, CATEGORY, and TAGS. Do not re-research; only search the web if you need a specific fact (real specs, current price) to avoid fabricating.

## The Four Voices
Stay rigidly in each voice. Vocabulary, sentence length, personality quirks, and depth must all match.

**Maya Reeves**
Precise but warm. Explains specs in real-world terms ("the 40ms latency means it feels instant"). Uses analogies to make tech approachable. Occasionally nerdy but never condescending. Sentences are slightly longer than Ken's. Loves a good "here's why that matters" moment.

**Ken Fujimoto**
Casual and punchy. Short sentences. Uses dollar amounts and percentages a lot ("saves you $40", "50% louder"). Dry humor. Never uses jargon without explaining it. Sounds like a Reddit power user who actually knows what they're talking about.

**Elizabeth Avery**
Writes about tech through a lens of creativity and art. Talks about freedom and creativity in tech. Spins all writing in a bubbly and light-hearted voice. Great flow, sentences carry rhythm. Might lean fluffy but always feels alive and joyful.

**Sam Johnson**
Conversational and opinionated. Talks about how things fit into daily life rather than specs. Uses "you'll love this if..." and "skip it if..." framing. Occasionally self-deprecating. Keeps things brief and values the reader's time visibly.

## Post structure (every post)
Each section must be separated by a markdown `---` horizontal rule.

- **Hook paragraph**: relatable problem or surprising fact, never "In this article we will…". No heading.

---

- **What it is**: **Required SEO heading.** Never use the generic label "What It Is". Write an H2 containing the product name or focus keyword: e.g. `## What Is the Anker 737 Power Bank?` or `## The Sony WH-1000XM5, Explained`. Plain-language, no jargon.

---

- **Who it's for**: **Required SEO heading.** Never use the generic label "Who It's For". Write an H2 that frames the audience around the product: e.g. `## Who Should Buy the Anker 737?` or `## Is the Bose QC45 Right for You?`. Specific personas and use cases.

---

- **Key features**: 3–5 bullets, benefits-first (these become the Pros). Heading flexible: use `## Key Features` only if nothing stronger fits, otherwise make it product-specific. Every H2 is a Google ranking signal, so prefer keyword-rich phrasing.

---

- **Honest take**: one downside or "not for you if…" (builds trust; this becomes the Con). Heading flexible: `## Honest Take`, `## One Thing to Consider`, or product-specific for better SEO.

---

- **FAQ**: 3 questions a real buyer would Google before purchasing. Format as:

  **Q: {question}**
  {2–3 sentence answer, written in the author's voice}

  Target "People Also Ask" style questions: comparisons, compatibility, "is it worth it", battery life, etc.

---

- **Verdict**: punchy 2-sentence wrap-up.

---

- **CTA**: a markdown link using the product's Amazon URL constructed from the ASIN:
  `[Check the current price on Amazon →](https://www.amazon.com/dp/{ASIN})`

## Writing rules
- Active voice, no filler words ("very", "really", "truly")
- Never start a sentence with "Overall" or "In conclusion"
- Amazon Associates disclosure is added automatically by the site, do NOT include it
- Title: 50–65 characters, compelling, contains the main keyword near the start
- Excerpt: 120–155 characters, includes the keyword, entices the click
- Body: 600–1000 words

**Banned patterns, zero exceptions across all four voices:**
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

**Naturalness rules:**
- Vary sentence length deliberately: mix short punchy sentences with longer explanatory ones.
- Use contractions throughout: "you'll", "it's", "doesn't", "that's".
- One concrete number or real-world comparison per section beats three vague adjectives.
- If a sentence could appear unchanged in any product review, rewrite it to be specific to this product.

## SEO self-check (before saving each post)
Score each post 0–100 against this checklist. If it scores below 75, revise the title, excerpt, and headings before saving:
- Title contains focus keyword near the start (50–65 chars)
- Excerpt 120–155 chars, includes keyword
- Focus keyword in first 100 words of body
- At least 2 H2/H3 subheadings contain related keywords
- Body 600–1200 words
- Slug short, lowercase, hyphenated, contains keyword

## Output file format
Save to `daily-drop/product-N.md` with the Write tool. One block per post, exactly this shape (field names are parsed by `bin/daily-drop-build.php`, keep them verbatim; BODY must be the last field in each block):

```
===POST===
AUTHOR: Maya Reeves
TITLE: {50–65 chars, contains keyword}
EXCERPT: {120–155 chars}
TYPE: article
CATEGORY: {from research.md, adjust only if clearly wrong}
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
SLUG: {seo-friendly-hyphenated-slug}
BODY:
{full markdown body: ## headings, --- dividers, **bold**, the CTA link. Plain markdown, no escaping, no code fences. Runs until the next ===POST=== line or end of file.}
```

Repeat the block for each voice (Maya Reeves, Ken Fujimoto, Elizabeth Avery, Sam Johnson) when writing all four.

## Done
Print to chat, nothing more:
```
✅ Product N written — {product name} ({M} post(s): {authors})
Saved to daily-drop/product-N.md
Next: run /drop-write {N+1}
```
After product 4, the next line is instead: `Next: run /drop-assemble`