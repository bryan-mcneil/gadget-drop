# Market-Wide Price Import (`market:import`)

The curated price layer (`products` + `product_price_snapshots`) tracks the few dozen review-backed products. The **market layer** (`market_products` + `market_price_snapshots`) tracks *every* ASIN ever seen in an import — a much wider dataset for category trends, deal discovery, and future features.

**The repo never scrapes anything.** `market:import` reads a **CSV or Excel `.xlsx`** file (first worksheet only) against the contract below and doesn't care what produced it — a Power Automate Desktop flow, a Keepa export, or a hand-edited sheet all work. In `.xlsx`, real date/time cells convert automatically — `scraped_at` needs no string formatting when it's a genuine datetime column. Whoever produces the file owns how it was produced (note: scraping Amazon violates their Conditions of Use regardless of affiliate status — that risk lives entirely outside this repo).

How the two layers connect: rows whose ASIN matches a curated `products.asin` also push their price through `ProductObserver` with `source = 'import'`, so the curated layer applies its own rules unchanged — snapshot on change, same-price check capped at one per day, `PriceIntel` cache flush. A scrape older than the product's `price_checked_at` is skipped (`stale_skipped` in the report) so it never regresses fresher API/manual data.

## CSV contract (of record)

Header rules: column names are matched **case- and order-insensitively**; a UTF-8 BOM on the first cell is stripped; missing any **required** column aborts the run naming it; unknown columns are ignored but listed in the report. UTF-16 output (a Power Automate misconfiguration) is detected and aborts with a pointer at the encoding setting.

| Column | Required | Validation |
|---|---|---|
| `asin` | **yes** | trim, uppercase, `^[A-Z0-9]{10}$` — else the row is rejected |
| `title` | **yes** | non-empty after trim; truncated to 500 chars. May be the **whole scraped tile block** — see the split rule below |
| `price` | **yes** | `$`, commas, spaces stripped → numeric > 0 — else rejected (ranges, "See price in cart") |
| `currency` | no | blank = USD; anything other than USD rejects the row |
| `description` | no | free text, truncated to 500. When absent/blank, it is **derived from `title`** by the split rule |
| `brand` | no | free text, truncated to 255 |
| `list_price` | no | same cleaning as price; invalid → nulled + counted in `warnings` |
| `rating` | no | numeric 0–5; invalid → nulled + counted |
| `review_count` | no | commas stripped → integer ≥ 0; invalid → nulled + counted |
| `category` | no | free text, truncated to 255 |
| `url` | no | must start `https://`; invalid → nulled + counted; truncated to 500 |
| `scraped_at` | no | ISO 8601 or `Y-m-d H:i:s` (assumed UTC); invalid → nulled + counted; blank → import time |

**Title-block split rule.** Scrapers usually capture a product tile's whole text as one block. When a row has no explicit `description`, the importer splits `title` at its first natural boundary — a dash or pipe **with spaces around it** (`" - "`, `" – "`, `" — "`, `" | "`; hyphenated words like "65-Inch" or "WH-1000XM5" never split) or `", "` as fallback. First segment → `title`, second segment → `description`, the rest is dropped:

> `Oura Ring 5 Sizing Kit - Size Before You Buy Oura Ring 5 - Unique Sizing, Not Standard Ring Sizing - …`
> → title `Oura Ring 5 Sizing Kit`, description `Size Before You Buy Oura Ring 5`

A block with no boundary stays a full title with a null description. A supplied `description` column disables the split for that row (title kept verbatim). The heuristic lives in `MarketImportService::splitTitleBlock()`.

Behavior notes:

- **Bad data rows never abort the run** — they're rejected per-row with a reason (first 5 echoed to the console; up to `rejects_cap` kept in the report artifact, full count always in `totals.rejected`).
- **Duplicate ASINs within one file**: first occurrence wins, the rest count as `duplicates` (listing pages repeat ASINs in sponsored slots/carousels).
- **Market snapshots are change-only**: a re-import at the same price advances `last_seen_at` but writes no snapshot row, so table growth follows price volatility, not run count. (This differs from the curated layer's once-a-day confirmed-check rule, which exists for the Truth Report observation gate; the market layer's `last_seen_at` carries that truth instead.)

## Usage

```
php artisan market:import path/to/market-2026-07-21.xlsx --dry-run  # validate + simulate, writes nothing
php artisan market:import path/to/market-2026-07-21.xlsx            # real run + report artifact
```

`.csv` and `.xlsx` are both accepted (extension decides the parser).

Dry-run executes the full import (including the curated merge) inside a transaction that always rolls back, so validation is identical to a real run.

## Run report

Each real run prints a console headline and writes a pretty-printed JSON artifact to `storage/app/market/import-{Y-m-d-His}.json` (gitignored). The full schema is documented in `App\Support\MarketReport`'s docblock; highlights: `totals` (rows/imported/rejected/duplicates), `asins` (new/known/total tracked), `curated` (the merge outcome), `movers` (price drops ≥ `mover_drop_pct` — full `total` plus the biggest drops `listed`, capped), `new_lows` (prices below the ASIN's tracked minimum, same total+listed shape), `coverage` (staleness + category counts), `rejects`, `warnings`, `ignored_columns`. Detail lists are capped but every count is a full count — no silent truncation. Thresholds and caps live in `config/market.php`. Old artifacts can be pruned by hand whenever; nothing reads them back.

One forward-looking rule: `market_products.url` stores raw Amazon URLs as **data** — nothing renders them today. If any future public surface ever lists market products, links must go through `route('affiliate.redirect', …)` with `rel="nofollow sponsored"` like every other Amazon link, never the raw URL.

## Power Automate Desktop flow outline (Bryan's side)

A PA **Desktop** flow driving a real browser session is the workable route (cloud flows get bot-blocked quickly). Outline:

1. Launch browser → the Amazon electronics category/best-seller pages to cover.
2. Per page, **Extract data from web page** into a table: ASIN (from the tile's `data-asin` attribute or the `/dp/{ASIN}/` URL segment), the tile's **whole title text as one block** (goes in `title`; the importer does the title/description split — do NOT split it in the flow), price, rating, review count.
3. Add `scraped_at` per page — a real datetime value in Excel output, or `%CurrentDateTime%` formatted `yyyy-MM-dd HH:mm:ss` in CSV. A `category` label per page is optional. Extra columns like `Source`/`Rank` can stay — unknown columns are ignored.
4. Loop pagination; append each page's rows to one table.
5. Write the file: **Excel `.xlsx`** ("Write to Excel worksheet", header row as row 1, one sheet) or **CSV** (header row ON, quote-all ON, UTF-8). Filename `market-YYYY-MM-DD.xlsx`/`.csv`.
6. Deliver the file to the machine that runs artisan (synced folder or manual copy), then run `market:import` — dry-run first.

No in-flow cleanup needed: the importer strips `$` and commas itself, splits the title block, and rejects malformed rows with reasons rather than breaking the run. Expect brittleness — selectors break and CAPTCHAs interrupt; the importer's job is to make a partial or messy file safe to load.
