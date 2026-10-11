---
name: raw-amazon-blocker-scope
description: The "raw amazon.com URL = BLOCKER" rule targets rendered/output surfaces bypassing /out — NOT the Product.affiliate_url column or its test fixtures
metadata:
  type: feedback
---

The raw-amazon-link BLOCKER rule applies to **output surfaces** (Blade views,
mails, MCP/JSON responses, anything a user or crawler sees) that link to
amazon.com directly instead of routing through `route('affiliate.redirect', …)`
→ `/out/{product}`.

**Why:** `Product.affiliate_url` is the canonical DB-stored redirect target that
`/out/{product}` reads and appends the tag to. Storing/seeding an `amazon.com`
URL there is correct and expected. Product factories/fixtures and migrations that
set `affiliate_url = 'https://www.amazon.com/dp/…'` are NOT violations.

**How to apply:** before flagging a raw `amazon.com` match, check WHERE it lives.
Column assignment / model factory / test fixture (e.g. `Product::create([... 'affiliate_url' => 'https://www.amazon.com/dp/…'])`)
= fine. Rendered link/href/JSON field shown to users without going through the
redirect = BLOCKER. Seen clean in `tests/Feature/TruthReportTest.php` (plan 05).
