# /drop-tip — Write Today's Tech Tip (Tue + Sat)

## Context
GadgetDrop (gadgetdrop.tech) publishes a tech tip twice a week (see the cadence table in `/daily-drop`). A tech tip solves ONE concrete, searchable problem — the kind a frustrated person Googles at 11pm ("fix slow wi-fi windows 11", "stop iphone battery draining overnight"). You research the tip, write it, and save it as plain structured markdown to `daily-drop/tip-1.md`. Do NOT write any JSON; `/drop-assemble` converts the file with a script. No prior conversation context needed. Keep chat output to short status lines.

Editorial and AdSense rules live in `CONTENT-GUIDELINES.md` (§ Tech Tips); the essentials are inlined below so this file works standalone.

## Step 1 — Find the tip
Search the web for a problem worth solving this week:
- Reddit: r/techsupport, r/HomeNetworking, r/iphone, r/Android, r/pcmasterrace hot/top threads
- "How to fix" trends around recent OS updates, popular devices, seasonal issues
- Problems adjacent to products GadgetDrop reviews (earbuds pairing, smart-home setup, monitor settings) — those cross-link naturally

**Filter for:** a real, common, searchable problem · a fix that actually works (verify the steps against at least one authoritative source) · relevant to one of the 6 site categories. Record the best source thread/article URL — it becomes `SOURCE_URL` (required; the public page renders the attribution).

## Step 2 — Write it
Voice: **Bryan McNeil** — plain-English, direct, a little dry, second person throughout ("open Settings", "you'll see").

Structure:
- **Hook**: name the symptom in one or two sentences, promise the fix. No heading.
- **`## {keyword-rich H2 naming the fix}`**: numbered steps, one action per step, exact menu paths and setting names. This is the core.
- **Why this works**: short paragraph, plain-language cause.
- **`## If That Didn't Work`**: 2–3 fallback fixes, ordered cheapest-first.
- **Pro tips**: 2–3 bullets that prevent the problem recurring. If a GadgetDrop-reviewed product genuinely helps, link the review site-relative (`[our X review](/posts/slug)`) — never an Amazon link.

Rules (same as every post type):
- 600–1000 words. Under 600 reads as thin content to Google; pad with real fallback fixes and prevention, never filler.
- No first-hand-testing claims ("we tested" etc. — see the banned list in `/drop-write`). "This fix comes up repeatedly in r/techsupport threads" is honest attribution.
- No em dashes, no banned phrases ("dive into", "game-changer", "seamlessly", …), no sentences opening with "Additionally,"/"Furthermore,".
- No Amazon links in the body. No affiliate CTA at all — tips build trust and topical breadth, not clicks.
- Title 50–65 chars with the search phrase near the start. Excerpt 120–155 chars.

## Output file format
Save to `daily-drop/tip-1.md` with the Write tool. **Exactly one block** (field names parsed by `bin/daily-drop-build.php`; BODY must be last; no ASIN/RATING/PROS/CONS — those are review-only):

```
===POST===
AUTHOR: Bryan McNeil
TITLE: {50–65 chars, search phrase near the start}
EXCERPT: {120–155 chars}
TYPE: tech_tip
CATEGORY: {one of: Audio & Home Theater | Smart Home | Computers & Accessories | Gaming | Wearables | Cameras — never invent a category}
TAGS: {tag1 | tag2 | tag3 | tag4}
SOURCE_URL: {https://... the thread/article the tip is sourced from — required}
SEO_SCORE: {0–100 self-check}
META_TITLE: {≤70 chars, keyword-first}
META_DESCRIPTION: {120–155 chars, includes keyword}
FOCUS_KEYWORD: {the search phrase}
SLUG: {seo-friendly-hyphenated-slug}
BODY:
{full markdown body. Runs until end of file.}
```

## Done
Print to chat, nothing more:
```
✅ Tech tip written — {title}
Saved to daily-drop/tip-1.md
Next: run /drop-assemble
```
