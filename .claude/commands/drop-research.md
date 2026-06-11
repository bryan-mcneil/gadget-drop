# /drop-research — Daily Drop Step 1: Find 4 Products

## Context
GadgetDrop (gadgetdrop.tech) is an Amazon affiliate site for tech and gadgets. This is **step 1 of the daily content pipeline**. You research today's 4 best products and save them to `daily-drop/research.md`. Later steps (`/drop-write`, `/drop-assemble`) read that file, so this command needs no prior conversation context. Keep chat output to short status lines; all content goes to the file.

## Preflight
1. If `daily-drop/research.md` already exists and its `DATE:` line is today, tell the user research is already done for today and ask whether to redo it (overwrite) or keep the existing picks. Stop and wait for the answer.
2. If any `daily-drop/product-*.md` files exist, they belong to a previous run. Tell the user and ask whether to delete them before continuing. Do not delete without confirmation.

## Step 1 — Fetch already-reviewed products

Retrieve the list of products already published on GadgetDrop so you do not pick duplicates.

Use the Bash tool to run:
```bash
php -r "echo file_get_contents(getenv('API_URL') . '/api/reviewed-products', false, stream_context_create(['http' => ['header' => 'X-API-Key: ' . getenv('GADGETDROP_API_KEY')]]));"
```

If the Bash tool is unavailable, use WebFetch:
- URL: `https://gadgetdrop.tech/api/reviewed-products`
- Header: `X-API-Key: {value of GADGETDROP_API_KEY from .env}`

Parse the JSON response: a list of `{ name, asin }` objects. **Do not pick any product whose ASIN or name appears in this list.**

If the API call fails, note it in the research file and continue without filtering.

## Step 2 — Search the web

Search the web for today's most compelling tech products with Amazon affiliate potential:

- Amazon Best Sellers in Electronics
- Reddit r/gadgets, r/BuyItForLife, r/tech hot posts
- TechRadar, The Verge, Wirecutter published this week
- Kotaku, IGN, Polygon (for more gaming and entertainment focus)
- Any viral or newly released tech products

**Filter for:**
- Has an Amazon listing with an ASIN
- Priced $20–$500
- Currently trending or newly released
- Strong buyer-intent search volume
- NOT already in the reviewed-products list from Step 1
- No accessories for now (we have plenty already)

Pick exactly **4 products**.

## Step 3 — Save the research file

Write `daily-drop/research.md` using the Write tool, in exactly this format (the later steps parse these field names):

```
DATE: {today, YYYY-MM-DD}
DEDUPE: {checked against N existing products | API unavailable, no dedupe}

===PRODUCT 1===
NAME: {exact Amazon listing name}
ASIN: {B0XXXXXXXXX}
PRICE: {approximate price, e.g. $129}
TRENDING: {why it's trending, 1 sentence}
ANGLE: {best post angle / hook}
KEYWORD: {primary target keyword}
CATEGORY: {Computers | Monitors | Smart Home | Gaming | Productivity | Photography | Audio | Wearables | etc.}
TAGS: {tag1 | tag2 | tag3 | tag4 | tag5}

===PRODUCT 2===
{same fields}

===PRODUCT 3===
{same fields}

===PRODUCT 4===
{same fields}
```

## Done

Print to chat, nothing more:
```
✅ Research complete — [Product 1], [Product 2], [Product 3], [Product 4] (checked against N existing products)
Saved to daily-drop/research.md
Next: run /drop-write 1 (you can /handoff to a cheaper model first — each step reads its input from disk)
```