# GadgetDrop Content Guidelines

Single source of truth for the three post types. The pipeline skills (`/drop-write`, `/drop-tip`, `/drop-news`) inline the essentials; when this file and a skill disagree, fix the skill. The build script (`bin/daily-drop-build.php`) enforces the mechanical rules; a human (Bryan) reviews and publishes everything.

**Why these rules exist:** the site was denied AdSense for (a) unverifiable first-hand-testing claims, (b) thin/doorway pages, and (c) near-duplicate multi-persona content. Every rule below traces back to keeping the site approvable and cohesive.

## The three types at a glance

| | Review (`article`) | Tech Tip (`tech_tip`) | Tech News (`tech_news`) |
|---|---|---|---|
| Purpose | Research-based buying verdict for one product | Solve one concrete, searchable problem | Buyer-relevant news + a committed verdict |
| Cadence | Daily | Tue + Sat | Mon + Thu (picked same morning — freshness) |
| Words | 900–1500 | 600–1000 | 600–900 |
| Skill | `/drop-write` | `/drop-tip` | `/drop-news` |
| File | `daily-drop/product-N.md` | `daily-drop/tip-1.md` | `daily-drop/news-1.md` |
| Must have | ASIN, rating, pros/cons, internal `/posts/` link, price-context section | `SOURCE_URL` (hard error without), numbered steps, second person, fallback fixes | `SOURCE_URL` (hard error), `## Buy or Wait?` section (hard error), original analysis |
| Affiliate CTA | The product card above the article — the ONLY one | None | None |
| Public rendering | Product card + price-history widget + verdict box | Emerald accent, Reddit/source attribution footer | Rose accent, "Source: {domain}" footer, `/news` listing |

## Rules that apply to every post (AdSense-critical)

1. **Honesty about testing.** GadgetDrop reviews are research-based (public statement: `/how-we-review`). Never claim first-hand testing. The banned-phrase list is canonical in `config/content.php` (`testing_claim_phrases`), hand-mirrored in `bin/daily-drop-build.php`. Attribute instead: "verified-purchase owners consistently report…", "professional testers measured…", "the manufacturer rates it at…".
2. **No invented numbers.** No fabricated price history, benchmark figures, or "was $199" claims. The site renders its own tracked-price widget; `App\Support\PriceIntel` gates every verdict behind real data (≥2 snapshots over ≥14 days).
3. **Affiliate discipline.** No Amazon links in any post body, ever. Reviews get exactly one CTA: the product card, which routes through `/out/{product}` (tag appended automatically, `rel="nofollow sponsored"`). Tips and news get none — they exist for trust, topical breadth, and freshness, not clicks.
4. **Category discipline.** Exactly six hubs: Audio & Home Theater, Smart Home, Computers & Accessories, Gaming, Wearables, Cameras. Never invent a category — the importer maps retired slugs back to hubs (`config('site.category_map')`), and thin categories were half the AdSense denial.
5. **One real author.** Byline is always Bryan McNeil (`config('site.author')`). The persona system is retired; its URLs 301 to the real author.
6. **Internal linking.** Reviews must link at least one other GadgetDrop review (the How-it-compares section). Tips/news link reviews where genuinely relevant. Site-relative markdown links only: `[our X review](/posts/slug)`.
7. **Style bans** (build script warns): em dashes; "dive into", "deep dive", "game-changer", "worth noting", "seamlessly", "unleash", "unlock/elevate your", "robust" (as feature adjective), "cutting-edge", "at the end of the day", "in today's world/fast-paced", "look no further"; sentences opening with "Additionally," or "Furthermore,"; passive "is designed to".
8. **SEO fields** (build script warns): title 50–65 chars with the keyword near the start · excerpt 120–155 · meta title ≤70 · meta description 120–155 · slug short, hyphenated, keyword-first · focus keyword in the first 100 words.
9. **Word-count floors are thin-content protection.** Never pad to hit them — earn the length with fallback fixes (tips), analysis (news), or comparison depth (reviews). A post that can't reach its floor honestly is the wrong topic; pick another.
10. **Image captions are optional (Plan 10.1).** The three inline images (`image_1/2/3`) each take an optional one-line caption, entered in the admin post form and rendered as a centred `<figcaption>` under the figure. The daily pipeline doesn't write captions and `posts:import` passes them through only when present, so a blank caption is normal — add one in the admin only when it adds context a reader wouldn't get from the image alone (a spec call-out, a "shown: X" clarifier), never filler. Images are placed automatically between paragraphs; don't hand-position them in the body.

## Per-type notes

### Reviews (`article`)
Structure (each section split by `---`): hook → What it is (keyword H2) → Who it's for → Key features in context (3–5 bullets → become Pros) → Price context (qualitative only; point at the price widget) → How it compares (1–2 real alternatives, internal links) → Honest take (→ becomes the Con; every review names at least one real downside) → FAQ (3 People-Also-Ask questions) → Verdict (buy/wait/skip lean) → closing nudge (no link). Honest editorial ratings: everything at 4.5+ destroys credibility.

### Tech Tips (`tech_tip`)
One problem, one page. Exact menu paths and setting names; steps someone can follow at 11pm. Why-it-works paragraph, "If That Didn't Work" fallbacks, prevention bullets. Source thread/article stored in `source_url` and rendered as attribution. A reviewed product may be linked when it genuinely solves the problem — review link, never Amazon.

### Tech News (`tech_news`)
Buyer-decision framing only (price cuts, launches, recalls, spec bumps). Inverted pyramid; most of the words go to original what-it-means-for-buyers analysis, never a wholesale rewrite of the source (quote at most one short phrase). Cite the source (`source_url`, rendered as "Source: {domain}"). The `## Buy or Wait?` section is the format's signature and is enforced by the build script.

### Truth Report posts (a `tech_news` variant — NOT a fourth type)
The editorial wrapper around a published `/truth/{slug}` data page (plan: `docs/plans/05-truth-report.md`). Created **by hand via `/admin` as a normal `tech_news` post** after the event report goes live — it is not on the daily-drop cadence and does not pass through `bin/daily-drop-build.php`. Never add a new `posts.type` value for it.

Tone rules:
1. **Grade deals, never Amazon.** A repackaged deal is a marketing decision somewhere upstream, and the same product is often a genuine bargain a month later. We publish math, not outrage — no retailer-hostility framing (Associates-safe by design).
2. **The number IS the headline.** "38% of the Prime Day deals we tracked were real" — never bait ("Amazon's FAKE sale EXPOSED" is an instant rewrite).
3. **Every claim cites the data page.** Any stat in the body must appear on `/truth/{slug}`; link the page in the first 100 words as the canonical source. The body quotes 3–4 headline stats and stops — the page is the dataset, the post is the story.
4. **Denominator-forward.** Say how many products we could judge and how many we couldn't, the first time a stat appears. Thin coverage isn't a weakness to hide; it IS the story ("we could only verify N — here's why that matters").
5. **Service section required.** `## Buy or Wait?` (the tech_news signature) becomes the "what to do about it" close: what's *currently* below typical on [/deals](/deals), how the grading works ([/how-we-review](/how-we-review)), and a Join the Drop nudge. Analysis without a next step is a takedown, not service journalism.

Mechanics: `source_url` = the `/truth/{slug}` URL (renders "Source: gadgetdrop.tech" — the source is our own tracked data; say so in the body). All standing rules apply: no Amazon links in the body, no invented numbers, internal review links where products are named, Bryan's byline.

## Enforcement map

| Rule | Enforced by |
|---|---|
| Field formats, lengths, banned phrases, testing claims, em dashes | `bin/daily-drop-build.php` (warnings) |
| Missing TITLE/BODY, unknown TYPE, tip/news SOURCE_URL, news Buy-or-Wait | `bin/daily-drop-build.php` (hard errors, exit 1) |
| Type whitelist, drafts-only, category map, single author | `DailyDropImporterService` + `posts:import` |
| Testing claims on published posts | `php artisan content:flag-claims` (QA gate in `/morning`) |
| Single CTA, noindex rules, persona 301s | test suite (`AdsensePrepTest`, `ThinPageCleanupTest`, `SitemapTest`) |
| Final judgment | Bryan — nothing publishes without a human pass |
