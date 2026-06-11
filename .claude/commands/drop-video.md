# /drop-video — Daily Drop Step 4 (Optional): Video & Social Assets

## Context
GadgetDrop daily content pipeline, **optional step 4**. Generates YouTube titles, a 90-second script, a description, and short-form social captions for researched products, appended to `daily-drop-output.md`. This command needs no prior conversation context. Keep chat output to short status lines; all content goes to the file.

## Arguments
`$ARGUMENTS` is a product number (1 to 4) or `all`. Default: `all`.

## Preflight
- Read `daily-drop/research.md` for each requested product's NAME, ASIN, PRICE, KEYWORD, CATEGORY.
- Read `daily-drop/product-N.md` for facts to reuse: key features, the honest take (the on-camera downside), and concrete numbers. Do not invent specs that appear in neither file.
- If `daily-drop-output.md` does not exist, tell the user to run `/drop-assemble` first and stop.

## Voice
Use **Ken Fujimoto's voice** for all scripts: casual, punchy, direct, best for spoken-word video. Short sentences. Dollar amounts and percentages. Dry humor.

**Script rules:**
- No em dashes, no banned phrases (same list as /drop-write)
- Contractions throughout
- Aim for 180–220 words (spoken at normal pace = ~90 seconds)
- Mention the price naturally ("it's $X on Amazon right now")
- One genuine downside per script, this builds credibility on camera

## Output
For each requested product, append this section to `daily-drop-output.md` (after the JSON block) using the Edit tool:

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
One concrete number or comparison per paragraph.
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

## Done
Print to chat, nothing more:
```
✅ Video assets appended for: {product names}
For videos: copy each script into ElevenLabs → import audio into Pictory → export and upload.
```