# /daily-drop — Full Content Pipeline

You are running the complete GadgetDrop daily content pipeline in one pass:
**Research → Write (4 voices) → SEO** for 4 products.

All output must be saved to `daily-drop-output.md` in the project root using the Write tool.
Do not print the full content in chat — only print a short status line after each phase completes.

---

## Phase 1 — Research (find 4 products)

Search the web for today's most compelling tech products with Amazon affiliate potential:

- Amazon Best Sellers in Electronics
- Reddit r/gadgets, r/BuyItForLife, r/tech hot posts
- TechRadar, The Verge, Wirecutter published this week
- Any viral or newly released tech products

**Filter for:**
- Has an Amazon listing with an ASIN
- Priced $20–$500
- Currently trending or newly released
- Strong buyer-intent search volume

Pick exactly **4 products**. For each note:
- Product name (exact Amazon listing name)
- ASIN (B0XXXXXXXXX)
- Why it's trending (1 sentence)
- Best post angle / hook
- Primary target keyword
- Suggested GadgetDrop category (Audio, Smart Home, Wearables, Accessories, Gaming, Productivity, Photography, etc.)
- 3–5 suggested tags

Print to chat: `✅ Phase 1 complete — [Product 1], [Product 2], [Product 3], [Product 4]`

---

## Phase 2 — Write (4 voices × 4 products = 16 posts)

For each of the 4 products, write a complete publish-ready post in each of the following voices.
Stay rigidly in each voice — vocabulary, sentence length, personality quirks, and depth must all match.

---

### The Four Voices

**Maya Reeves**
Precise but warm. Explains specs in real-world terms ("the 40ms latency means it feels instant"). Uses analogies to make tech approachable. Occasionally nerdy but never condescending. Sentences are slightly longer than Ken's. Loves a good "here's why that matters" moment.

**Ken Fujimoto**
Casual and punchy. Short sentences. Uses dollar amounts and percentages a lot ("saves you $40", "50% louder"). Dry humor. Never uses jargon without explaining it. Sounds like a Reddit power user who actually knows what they're talking about.

**Elizabeth Avery**
Writes about tech through a lens of creativity and art. Talks about freedom and creativity in tech. Spins all writing in a bubbly and light-hearted voice. Great flow — sentences carry rhythm. Might lean fluffy but always feels alive and joyful.

**Sam Johnson**
Conversational and opinionated. Talks about how things fit into daily life rather than specs. Uses "you'll love this if..." and "skip it if..." framing. Occasionally self-deprecating. Keeps things brief and values the reader's time visibly.

---

### Post structure for each version

Each section must be separated by a markdown `---` horizontal rule.

- **Hook paragraph** — relatable problem or surprising fact, never "In this article we will…"

---

- **What it is** — plain-language, no jargon dumps

---

- **Who it's for** — specific personas and use cases

---

- **Key features** — 3–5 bullets, benefits-first

---

- **Honest take** — one downside or "not for you if…" (builds trust)

---

- **FAQ** — 3 questions a real buyer would Google before purchasing. Format as:

  **Q: {question}**
  {2–3 sentence answer, written in the author's voice}

  Target "People Also Ask" style questions: comparisons, compatibility, "is it worth it", battery life, etc.

---

- **Verdict** — punchy 2-sentence wrap-up

---

- **CTA** — A markdown link using the product's Amazon URL constructed from the ASIN:
  `[Check the current price on Amazon →](https://www.amazon.com/dp/{ASIN})`

**Additional rules:**
- Active voice, no filler words ("very", "really", "truly")
- Never start a sentence with "Overall" or "In conclusion"
- Amazon Associates disclosure is added automatically by the site — do NOT include it
- Title: 50–65 characters, compelling, contains the main keyword
- Excerpt: 120–155 characters, entices the click

Print to chat after each product completes: `✅ Product [N] written — [name]`

---

## Phase 3 — SEO fields (for every post)

For each of the 16 posts, generate copy-paste-ready SEO values and a score.

**SEO scoring checklist:**
- Title contains focus keyword near the start (50–65 chars)
- Excerpt/meta description 120–155 chars, includes keyword and CTA
- Focus keyword in first 100 words of body
- Keyword density 1–2%
- At least 2 H2/H3 subheadings with related keywords
- Body 600–1200 words
- Slug is short, lowercase, hyphenated, contains keyword
- Purchase-intent keyword ("best", "review", "vs", price terms)

If a post scores below 75, include a revised title and revised hook paragraph inline.

---

## Output file format

Save everything to `daily-drop-output.md` using this exact structure:

```
# GadgetDrop Daily Drop — {today's date}

════════════════════════════════════════
## PRODUCT 1: {Product Name}
ASIN: {B0XXXXXXXXX}
Trending because: {1 sentence}
Category: {category}
Tags: {tag1, tag2, tag3, tag4, tag5}
════════════════════════════════════════

### ✍️ MAYA REEVES

**FORM FIELDS — copy-paste into admin:**
| Field | Value |
|---|---|
| Title | {title} |
| Slug | {slug} |
| Excerpt | {excerpt} |
| Focus Keyword | {keyword} |
| Meta Title | {meta title ≤70 chars} |
| Meta Description | {meta description 120–155 chars} |
| Category | {category} |
| Tags | {tags} |
| SEO Score | {score}/100 |

**POST BODY:**
{full markdown body}

---

### ✍️ KEN FUJIMOTO

**FORM FIELDS — copy-paste into admin:**
| Field | Value |
|---|---|
| Title | {title} |
| Slug | {slug} |
| Excerpt | {excerpt} |
| Focus Keyword | {keyword} |
| Meta Title | {meta title ≤70 chars} |
| Meta Description | {meta description 120–155 chars} |
| Category | {category} |
| Tags | {tags} |
| SEO Score | {score}/100 |

**POST BODY:**
{full markdown body}

---

### ✍️ ELIZABETH AVERY

**FORM FIELDS — copy-paste into admin:**
...

---

### ✍️ SAM JOHNSON

**FORM FIELDS — copy-paste into admin:**
...

---
{repeat for Products 2, 3, 4}
```

After saving the file, print to chat:
```
✅ Daily drop complete — daily-drop-output.md is ready.
4 products · 16 posts · all SEO fields generated.
Pick your favourite voice for each product and paste into the admin panel.
```
