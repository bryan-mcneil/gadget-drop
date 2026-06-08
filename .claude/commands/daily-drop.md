# /daily-drop — Full Content Pipeline

You are running the complete GadgetDrop daily content pipeline in one pass:
**Research → Write (4 voices) → SEO** for 4 products.

All output must be saved to `daily-drop-output.md` in the project root using the Write tool.
Do not print the full content in chat — only print a short status line after each phase completes.

---

## Phase 1 — Research (find 4 products)

### Step 1a — Fetch already-reviewed products

Before searching, retrieve the list of products already published on GadgetDrop.

Use the Bash tool to run:
```bash
php -r "echo file_get_contents(getenv('APP_URL') . '/api/reviewed-products', false, stream_context_create(['http' => ['header' => 'X-API-Key: ' . getenv('GADGETDROP_API_KEY')]]));"
```

If the Bash tool is unavailable, use WebFetch:
- URL: `https://gadgetdrop.tech/api/reviewed-products`
- Header: `X-API-Key: {value of GADGETDROP_API_KEY from .env}`

Parse the JSON response. Keep the list of `{ name, asin }` objects in memory. **Do not pick any product whose ASIN or name appears in this list.**

If the API call fails, note it and continue without filtering.

### Step 1b — Search the web

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
- **NOT already in the reviewed-products list from Step 1a**

Pick exactly **4 products**. For each note:
- Product name (exact Amazon listing name)
- ASIN (B0XXXXXXXXX)
- Why it's trending (1 sentence)
- Best post angle / hook
- Primary target keyword
- Suggested GadgetDrop category (Audio, Smart Home, Wearables, Accessories, Gaming, Productivity, Photography, etc.)
- 3–5 suggested tags

Print to chat: `✅ Phase 1 complete — [Product 1], [Product 2], [Product 3], [Product 4] (checked against N existing products)`

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

- **Hook paragraph** — relatable problem or surprising fact, never "In this article we will…". No heading.

---

- **What it is** — **Required SEO heading.** Never use the generic label "What It Is". Write an H2 containing the product name or focus keyword: e.g. `## What Is the Anker 737 Power Bank?` or `## The Sony WH-1000XM5, Explained`. Plain-language, no jargon.

---

- **Who it's for** — **Required SEO heading.** Never use the generic label "Who It's For". Write an H2 that frames the audience around the product: e.g. `## Who Should Buy the Anker 737?` or `## Is the Bose QC45 Right for You?`. Specific personas and use cases.

---

- **Key features** — 3–5 bullets, benefits-first (these become the Pros). Heading flexible: use `## Key Features` if nothing stronger fits, otherwise make it product-specific.

---

- **Honest take** — one downside or "not for you if…" (builds trust; this becomes the Con). Heading flexible: `## Honest Take`, `## One Thing to Consider`, or product-specific.

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
- Write between 600-1000 words

**Banned patterns — apply across all four voices:**
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

Print to chat after each product completes: `✅ Product [N] written — [name]`

---

## Phase 3 — SEO + JSON output (for every post)

For each of the 16 posts, compute SEO values and assemble the final JSON object.

**SEO checklist per post:**
- Title contains focus keyword near the start (50–65 chars)
- Excerpt 120–155 chars, includes keyword
- Focus keyword in first 100 words of body
- At least 2 H2/H3 subheadings with related keywords
- Body 600–1200 words
- Slug short, lowercase, hyphenated, contains keyword

If a post scores below 75, revise the title and excerpt before writing the JSON.

---

## Output file format

Collect all 16 post objects and save to `daily-drop-output.md` as a single JSON array.
The body field must keep its full markdown formatting (## headings, --- dividers, **bold**, etc.) — JSON-encode it as a string.

```
# GadgetDrop Daily Drop — {today's date}

```json
[
  {
    "title": "{title, 50–65 chars, contains keyword}",
    "excerpt": "{excerpt, 120–155 chars}",
    "body": "{full markdown body as a single JSON string — ## headings, --- dividers preserved}",
    "type": "article",
    "author_name": "Maya Reeves",
    "category_name": "{Audio | Smart Home | Wearables | Gaming | Accessories | Productivity | Photography | etc.}",
    "tag_names": ["{tag1}", "{tag2}", "{tag3}", "{tag4}", "{tag5}"],
    "product_asin": "{B0XXXXXXXXX}",
    "rating": {4.0},
    "pros": ["{benefit 1, 5–10 words}", "{benefit 2}", "{benefit 3}"],
    "cons": ["{drawback 1, 5–10 words}"],
    "seo": {
	  "score": "{calculated total SEO score 0-100}"
      "meta_title": "{meta title ≤70 chars}",
      "meta_description": "{meta description 120–155 chars}",
      "focus_keyword": "{primary seo keyword}"
    }
  },
  {same structure for Ken Fujimoto},
  {same structure for Elizabeth Avery},
  {same structure for Sam Johnson},
  {repeat for Products 2, 3, 4 — 16 objects total}
]
```
```

**JSON encoding rules for the body field:** KODEE10","10PCT_48M_CSW8FFJF
- Escape all double quotes as `\"`
- Encode newlines as `\n`
- Encode tab characters as `\t`
- Do NOT wrap in a nested JSON object — the body value is a plain string

After saving the JSON array, append the following section to `daily-drop-output.md` for each of the 4 products, then print the completion message.

---

## Phase 4 — Video & Social Assets (per product)

For each of the 4 products, generate the following and append to `daily-drop-output.md` under a `### VIDEO ASSETS` header.

Use **Ken Fujimoto's voice** for all scripts (casual, punchy, direct — best for spoken-word video).

```
════════════════════════════════════════
### VIDEO ASSETS — {Product Name}
════════════════════════════════════════

**YouTube Title A (curiosity hook, <70 chars):**
{title}

**YouTube Title B (keyword-first, <70 chars):**
{title}

**90-Second Script:**
{3–4 short spoken paragraphs, each 2–4 sentences. Mark natural pauses with (PAUSE).
No em dashes. Contractions throughout. One concrete number or comparison per paragraph.
End with a direct CTA: "Link in the description."}

**YouTube Description (400 words):**
{Hook paragraph (2 sentences).

0:00 — Intro
0:20 — What is it?
0:45 — Who's it for?
1:10 — Key features
1:35 — Honest take
1:50 — Verdict

🔗 Check the price: https://www.amazon.com/dp/{ASIN}

{2-sentence Amazon Associates disclosure}

Subscribe for weekly tech picks → https://gadgetdrop.tech

#{keyword} #gadgets #techreview #amazon #{category}}

**Short-Form Captions:**

X/Twitter (280 chars max):
{punchy take + price + affiliate link short: https://www.amazon.com/dp/{ASIN} + 2-3 hashtags}

Instagram:
{2–3 sentence caption, conversational. 10 hashtags on a new line.}

Pinterest:
{product description 50–100 words, purchase-intent keywords, no hashtags}
```

**Script rules:**
- No em dashes, no banned phrases (same list as Phase 2)
- Aim for 180–220 words (spoken at normal pace = ~90 seconds)
- Mention the price naturally ("it's $X on Amazon right now")
- One genuine downside — this builds credibility on camera

---

After saving the file, print to chat:
```
✅ Daily drop complete — daily-drop-output.md is ready.
4 products · 16 posts · JSON array saved · video assets included.
Go to /admin/daily-drop, paste the JSON array, preview, then import all as drafts.
For videos: copy each script into ElevenLabs → import audio into Pictory → export and upload.
```
