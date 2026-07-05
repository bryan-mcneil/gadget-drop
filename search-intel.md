# Search Intel — Search-Engine API Integration Plan

> **Status:** Approved plan (decisions delegated to Claude, 2026-07-03). Built in reviewable phases — each phase lands green (`php artisan test`) before the next begins.
> v1 = Phases 0–5. Phase 6 is an à-la-carte frontier menu once the core loop is live.

---

## Context

GadgetDrop already has an autonomous content pipeline (research → write → assemble → import, 11 posts/week) and a price-intelligence layer. What it does **not** have is any feedback from search engines. Today the pipeline picks topics blind, published posts are never verified as indexed, non-Google engines find us whenever they get around to crawling, and nobody notices when a post's rankings decay.

**Search Intel closes the loop.** Search Console + Bing data flows into the same pipeline that writes the content:

```
GSC + Bing APIs ──► search:sync ──► search:mine ──► search:brief ──► daily-drop/seo-brief.md
      ▲                (daily)      (opportunities)      │                (committed by /morning)
      │                                                  ▼
  index checks ◄── search:inspect ◄── IndexNow + sitemap ping ◄── publish (PostObserver)
      │                                                  ▲
      └── unindexed alerts ──► /morning        cloud agent writes the post
                                               (research reads the brief)
```

Every other affiliate site guesses what to write. After this ships, the overnight agent reads **yesterday's actual demand signals** from two search engines, writes exactly what searchers are already asking for, the publish event pings every IndexNow engine within seconds, indexation is verified within days, and decaying posts get refreshed instead of forgotten. A self-optimizing organic-traffic flywheel — built from free, official, policy-safe APIs.

### What the research established (July 2026)

| Capability | Source | Facts that matter |
|---|---|---|
| Query/page/click/position data | **GSC Search Analytics API** | Free. 16 months history. Dimensions: `query, page, date, country, device, searchAppearance, hour`. Types: `web, discover, googleNews, news, image, video`. `rowLimit` 25,000/request + `startRow` paging. `dataState=all` includes fresh (partial) data; finalized data lags ~2–3 days. Quota 1,200 QPM/site — we'll use ~0.1% of it. |
| Hourly granularity | GSC API (April 2025 feature) | `HOUR` dimension + `dataState=HOURLY_ALL`, past **10 days** — launch telemetry for news posts. |
| Per-URL index status | **GSC URL Inspection API** | `verdict`, `coverageState`, `lastCrawlTime`, canonicals, rich-results status. Quota 2,000/day/site (we need ~10). |
| Sitemap submission | **GSC Sitemaps API** | `PUT .../sitemaps/{feedpath}` re-nudges Google after publish. |
| Bing performance data | **Bing Webmaster API** (free, single `apikey` per user) | JSON REST at `https://ssl.bing.com/webmaster/api.svc/json/{Method}?apikey=…`. `GetRankAndTrafficStats` (daily clicks/impressions), `GetQueryStats` / `GetPageStats` (rolling ~6-month per-query/per-page aggregates — **no date params**), keyword research (`GetKeyword`, `GetRelatedKeywords` — free volume data), crawl stats/issues. |
| Instant indexing everywhere-but-Google | **IndexNow** | One POST to `api.indexnow.org` fans out to **Bing, Yandex, Seznam, Naver, Yep**. Key = self-generated; verified via a key file (or `keyLocation` param). Bulk ≤10,000 URLs/POST. Free. **Google still does not participate (confirmed Feb 2026)** — Google gets sitemap pings instead. |
| AI answer engines | Bing index + AI search bots | Bing's index feeds Microsoft Copilot and (heavily) ChatGPT search; Perplexity crawls directly. Training bots and search bots are now separate UAs (`GPTBot` vs `OAI-SearchBot`, `ClaudeBot` vs `Claude-SearchBot`). Being IndexNow-fresh in Bing **is** AI-visibility strategy. |
| Trend data | **Google Trends API (alpha)** | Announced July 2025, still application-gated in mid-2026. 5-year rolling window, daily/weekly/monthly grouping, ~48h freshness. We apply now, integrate when granted (Phase 6). |
| DuckDuckGo | — | Rides Bing's index. IndexNow + Bing coverage handles it. Nothing to build. |
| Brave | — | Independent crawler, no webmaster/submission API. Nothing to build. |

### Deliberately NOT using

- **Google Indexing API** — restricted to `JobPosting`/`BroadcastEvent` schema. Using it for regular content is a known spam vector and a policy risk. Never.
- **GSC → BigQuery bulk export** — requires GCP billing. The plain API is unsampled at our size. Revisit only if we outgrow 25k rows/day (a great problem to have).
- **Unofficial endpoints** (Google Suggest scraping, SERP scraping) — ToS-gray and brittle. Bing's official keyword API covers expansion for free.
- **Scraping Amazon or Google** — existing hard rule, unchanged.

### Locked decisions

| Decision | Choice |
|---|---|
| Google auth | **Service account** (JSON key) added as a user on the GSC property — no OAuth refresh dance, headless-safe. Library: **`google/auth`** (small, official) + Laravel HTTP client. NOT the 30 MB `google/apiclient` — we call three endpoints. |
| Bing auth | API key (per-user, covers the site) as query param. Plain Laravel HTTP, no package. |
| IndexNow key | Env-driven (`INDEXNOW_KEY`), served by a route at `/indexnow.txt`; submissions pass `keyLocation`. Rotates via env, never committed. |
| Data grains | `search_site_days` (site/day trend), `search_page_days` (page/day), `search_query_days` (query×page/day, Google only), `bing_query_stats` (weekly snapshots of Bing's rolling aggregates — honest modeling of Bing's no-date-range API). |
| Where things run | Sync/mine/inspect on the **prod scheduler** (data + keys live there). The brief reaches the repo (for the overnight cloud agent) by **/morning fetching it over SSH and committing it** — no new public endpoints, no secrets in the cloud agent. Staleness ≤24h is fine; GSC finalized data lags ~2 days anyway. |
| Publish pings | `PostObserver` fires IndexNow + Google sitemap resubmit on publish/URL-change, inline (no queue worker on Hostinger), 3s timeout, try/catch, logged to `search_submissions`, gated by `SEARCH_PING_ENABLED`. |
| Bing URL Submission API | **Skipped.** Bing itself steers to IndexNow; SubmitUrlBatch would be redundant. |
| Mining | Runs on Google daily-grain data. Bing contributes trend totals, keyword volume enrichment, and a weekly "Bing-only demand" check. All thresholds in `config/search.php` (honesty gates, PriceIntel-style — no opportunity fires on noise). |
| Cadence | **Unchanged — 11 posts/week.** Search data changes *which* topics get written, never *how many*. (Scaled-content-abuse firewall.) |
| Sessions/phases | Each phase is one working session, reviewed before the next. |

---

## Policy guardrails (the contract)

This system must strengthen the AdSense reapplication, never endanger it. Google's own spam-policy docs list what a *good* affiliate site does: **"information about price, original product reviews, rigorous testing and ratings, product comparisons."** That is literally GadgetDrop's price-intel + review model — Search Intel just aims it at demonstrated demand.

Hard rules, enforced in code and skill prompts:

1. **No Google Indexing API.** Sitemap pings + natural crawling only on Google. (IndexNow is Bing/Yandex/etc. — fully sanctioned there.)
2. **Volume never scales with data.** The brief re-ranks candidate topics; the cadence table in `/daily-drop` stays the law. No "we found 40 gaps, generate 40 pages."
3. **Value gate before demand gate.** A topic from the brief is only picked if we can add genuine value (price history, testing, comparison). `/drop-research` keeps its editorial bar; the brief is an input, not an order.
4. **CTR fixes must stay truthful.** Title/meta rewrites optimize clarity and appeal — never clickbait, never claims the post doesn't support (AdSense misleading-content policy).
5. **Refreshes are real updates** — new price data, updated recommendations, expanded answers — with honest `updated_at`. Never date-bumping unchanged content.
6. **Cannibalization is resolved by merging/canonicalizing** — the opposite of doorway pages.
7. **Robots/crawler policy welcomes search & answer bots.** Distribution is the business; we do not block AI *search* crawlers. (Training-bot policy is a separate content-rights call — default: leave open, revisit if Bryan objects.)

---

## Phase 0 — Accounts & keys (Bryan, ~45 min, one-time)

The only phase requiring a human. Checklist:

1. **Google Cloud**: create project `gadgetdrop-search` → enable **"Google Search Console API"** → IAM → Service Accounts → create `search-intel@…` → Keys → Add key → JSON → download.
2. **Search Console** (`search.google.com/search-console`): Settings → Users and permissions → Add user → paste the service account's `client_email` → permission **Owner** (delegated owner; Full works for analytics but Owner future-proofs URL Inspection). Note which property type exists — **domain** (`sc-domain:gadgetdrop.tech`) or **URL-prefix** (`https://gadgetdrop.tech/`) — the API property string must match exactly.
3. **Bing Webmaster Tools** (`bing.com/webmasters`): sign in → **Import from Google Search Console** (one click, verifies instantly) → Settings → API Access → accept terms → **Generate API Key**.
4. **IndexNow key**: any 32-hex string — `php -r "echo bin2hex(random_bytes(16));"`.
5. **Key placement**: upload the Google JSON to `storage/app/keys/gsc-service-account.json` on the server (outside webroot), `chmod 600`. Same path locally for dev. Add `storage/app/keys/` to `.gitignore`.
6. **Env** (prod + local):
   ```
   GSC_PROPERTY=sc-domain:gadgetdrop.tech        # or the URL-prefix form from step 2
   GSC_CREDENTIALS_PATH=storage/app/keys/gsc-service-account.json
   BING_WEBMASTER_API_KEY=…
   INDEXNOW_KEY=…
   SEARCH_PING_ENABLED=true                      # prod only; absent/false locally
   ```
7. **Google Trends API alpha**: submit the application form (developers.google.com/search/apis/trends) — costs nothing, unlocks Phase 6 trend integration whenever granted.

**Done when:** `php artisan tinker` on prod can mint a token (`GoogleSearchConsoleService::token()` in Phase 1) and a curl to Bing's `GetUserSites` returns the site.

---

## Phase 1 — Plumbing: services, sync, backfill

**Goal:** 16 months of Google data + Bing data flowing into local tables daily, idempotently, with the suite green.

**New files:**
- `database/migrations/…_create_search_intel_tables.php` — all tables below, **sqlite-safe schema-builder types only** (no MySQL-only DDL → no driver guard needed)
- `app/Services/GoogleSearchConsoleService.php`, `app/Services/BingWebmasterService.php`
- `app/Models/` slim models per table
- `app/Console/Commands/SyncSearchData.php` (`search:sync {--days=5} {--backfill=}`)
- `config/search.php` (property, paths, thresholds), `config/services.php` entries
- `composer require google/auth`
- `tests/Feature/SearchSyncCommandTest.php`

**Tables** (all metrics: `clicks`, `impressions`, `position` decimal(6,2); CTR is computed, never stored):
- `search_site_days` — `source` (`google`|`bing`), `date`, `search_type` (`web`|`discover`|`googleNews`), unique `(source, date, search_type)`
- `search_page_days` — `date`, `page_url` (500), `url_hash` char(40), `post_id` nullable FK (resolved from URL, nullOnDelete), unique `(date, url_hash)`
- `search_query_days` — `date`, `query` (500), `query_hash`, `page_url`, `url_hash`, unique `(date, query_hash, url_hash)`; index `(query_hash, date)`, `(url_hash, date)`. **Hash columns exist because utf8mb4 unique indexes cap at ~768 chars** — sha1 the natural keys.
- `bing_query_stats` — `query`, `query_hash`, `clicks`, `impressions`, `avg_click_position`, `avg_impression_position`, `captured_on` (date), unique `(query_hash, captured_on)` — weekly snapshots of Bing's rolling 6-month aggregate.
- `search_submissions` — `url`, `engine` (`indexnow`|`google_sitemap`), `trigger` (`publish`|`update`|`retry`|`manual`), `response_code`, `submitted_at` (Phase 2 writes here; create now).
- `search_index_checks` — `post_id` FK, `url`, `verdict`, `coverage_state`, `last_crawl_at` nullable, `raw` json, `checked_at` (Phase 2 writes here; create now).
- `search_opportunities` — Phase 3 (create now): `kind`, `query`, `query_hash`, `post_id` nullable, `page_url` nullable, `score` decimal, `evidence` json, `status` (`open`|`planned`|`done`|`dismissed`), `first_seen_at`, `last_seen_at`, unique `(kind, query_hash, post_id)`.

**`GoogleSearchConsoleService`** (mirrors `CanopyApiService` discipline):
- `token()` — `google/auth` `ServiceAccountCredentials` (scope `https://www.googleapis.com/auth/webmasters`), cached on the **database cache store** for expiry−300s
- `searchAnalytics(array $body)` — `POST https://searchconsole.googleapis.com/webmasters/v3/sites/{property}/searchAnalytics/query`, auto-pagination via `startRow` (25k rows/page), `dataState=all`, retry w/ backoff on 429/5xx
- `submitSitemap()` — `PUT …/sites/{property}/sitemaps/{urlencoded sitemap.xml URL}`
- `inspectUrl(string $url)` — `POST https://searchconsole.googleapis.com/v1/urlInspection/index:inspect`
- **Graceful no-op** (returns null + one log line) when property/key unconfigured — local dev and CI never need creds.

**`BingWebmasterService`** — `GET https://ssl.bing.com/webmaster/api.svc/json/{Method}?apikey=…&siteUrl=…` wrappers: `rankAndTrafficStats()`, `queryStats()`, `keyword($q)`, `relatedKeywords($q)`. Same no-op discipline.

**`search:sync`** (schedule **daily 07:00 UTC** — after `prices:refresh` 06:00, per the minute-:00 hourly-cron rule):
1. Google, per day in window (default: last 5 days re-pulled — finalized data restates fresh data): site/day totals per `search_type` (web + discover + googleNews), page/day (`dimensions: [page]`), query×page/day (`dimensions: [query,page]`). Upsert everything (`ON DUPLICATE`-style `upsert()` on the unique keys).
2. `--backfill=480` variant walks 16 months in date-chunked requests (one-time, run manually after Phase 0).
3. Bing: `GetRankAndTrafficStats` → `search_site_days` (source `bing`); Sundays only, `GetQueryStats` → `bing_query_stats` snapshot.
4. Resolve `post_id` on page rows by matching URL path against post slugs.
5. Prune rows older than 16 months (GSC's own retention) — folded in here, no separate command.

**Tests:** `Http::fake` fixture responses → upsert idempotency (run twice, same counts), post_id resolution, no-op without creds, prune. Suite stays green on sqlite.

**Done when:** prod backfill completes; `search_site_days` matches the GSC UI totals for a spot-checked week.

---

## Phase 2 — Indexing hygiene: IndexNow, sitemap pings, inspection, crawler policy

**Goal:** every publish is announced to every engine that accepts announcements, indexation is verified, and the site is explicitly AI-search-friendly. (Only IndexNow + robots bits depend on Phase 0; this phase can ship independently of Phase 1.)

**New files:** `app/Services/IndexNowService.php`, `app/Observers/PostObserver.php` (or extend the existing Post `saved` hooks — decide at impl; observer preferred, registered in `AppServiceProvider`), `app/Console/Commands/InspectSearchIndex.php`, route additions, `tests/Feature/SearchPingTest.php`, `tests/Feature/SearchInspectCommandTest.php`.

**IndexNow:**
- Route `GET /indexnow.txt` → returns `config('search.indexnow_key')` as `text/plain` (404 when unset). Submissions POST to `https://api.indexnow.org/indexnow` with `host`, `key`, `keyLocation: https://gadgetdrop.tech/indexnow.txt`, `urlList` — one ping fans out to Bing, Yandex, Seznam, Naver, Yep.
- `IndexNowService::submit(array $urls)` — chunks ≤500, logs each to `search_submissions`, swallows failures.

**Publish hook (`PostObserver`):** on transition to `published`, and on slug/title change of a published post (submit old + new URL on slug change): IndexNow-ping the post URL + homepage + its category page, and `submitSitemap()` to Google. Inline (no queue), 3s timeout per call, try/catch — **a failed ping must never block a save**. Gated by `SEARCH_PING_ENABLED` so local/dev/test stay silent. `posts:import` creates *drafts*, so hooks fire on real publishes only — correct by construction.

**`search:inspect`** (schedule **daily 09:00 UTC**): inspect (a) posts published in the last 14 days, (b) any post whose last check wasn't "indexed", cap 50/day (quota: 2,000). Store to `search_index_checks`. Escalation: published >4 days and still not indexed → re-ping IndexNow, resubmit sitemap, mark for the /morning checklist.

**Crawler & discovery policy (same phase — small, high-leverage):**
- `<meta name="robots" content="max-image-preview:large">` in `layouts/public.blade.php` — **Google Discover eligibility**; gadget content with big images is Discover's exact diet, and we already serve 1600px variants.
- robots.txt: explicitly `Allow: /` for the AI **search/answer** bots (`OAI-SearchBot`, `Claude-SearchBot`, `PerplexityBot`, `bingbot`) above the general rules — declarative, self-documenting. Training bots stay implicitly allowed (locked decision #7).
- `llms.txt` route: markdown map of the site for LLMs — brand blurb, `/deals`, categories, latest 20 posts. Cached on the db store, busted by the existing `NavigationData::flush()` post-save hook.

**Tests:** observer pings logged when enabled / silent when disabled (Http::fake, assert zero requests); slug-change double-submit; `/indexnow.txt` + `/llms.txt` responses; inspect command stores verdicts + escalates. `PublicLayoutTest` gains the `max-image-preview` assertion.

**Done when:** a real publish on prod shows two `search_submissions` rows (indexnow 200, google_sitemap 200) and the post shows "indexed" in `search_index_checks` within days.

---

## Phase 3 — Intelligence: `SearchIntel` + opportunity mining

**Goal:** turn raw rows into a ranked, deduplicated, honesty-gated list of actions.

**New files:** `app/Support/SearchIntel.php` (the `PriceIntel` twin: static entry points, db-cached 6h, defensive try/catch, `flush()`), `app/Console/Commands/MineSearchOpportunities.php` (`search:mine`, schedule **daily 08:00 UTC** — between sync and inspect), `tests/Unit/SearchIntelTest.php`, `tests/Feature/SearchMineCommandTest.php`.

**Expected-CTR curve:** median CTR by rounded position computed from **our own** `search_query_days` (positions 1–20, min 200 impressions per bucket); config fallback curve for buckets without sample. Own-data curves beat industry tables because branding/SERP-features skew is site-specific.

**Detectors** — each returns `(kind, query cluster, target, score, evidence)`; **all thresholds in `config/search.php`**, tuned low for a young site, raised as data grows. Score = estimated monthly clicks gained (`impressions × (expectedCTR(target position) − actual CTR)`) — explainable, comparable across kinds:

| kind | Fires when (28-day window unless noted) | Action it implies |
|---|---|---|
| `striking_distance` | position 4–15, ≥30 impressions, page is one of our posts | Optimize existing post → page 1 |
| `ctr_fix` | position ≤12, CTR <50% of expected, ≥100 impressions | Rewrite title/meta (truthfully) |
| `content_gap` | query ≥20 impressions whose best page is home/category/off-topic post, or position >20 | New post candidate → feeds the brief |
| `decay` | post's clicks <60% of its prior 28-day window (prior ≥20 clicks) | Refresh the post |
| `cannibalization` | ≥2 posts each taking ≥20% of a query's ≥50 impressions | Merge / canonicalize / differentiate |
| `rising` | query impressions ≥2× week-over-week (≥10/wk), no dedicated post | News/tip angle for Mon/Thu/Tue/Sat slots |
| `bing_gap` | high Bing volume (`GetKeyword`) or strong `bing_query_stats` presence where Google position is weak/absent | Cross-engine arbitrage (report-only in v1) |

**Mining mechanics:** `search:mine` upserts on `(kind, query_hash, post_id)` — refreshes `score`/`evidence`/`last_seen_at` on re-detection, auto-resolves to `done` when the condition clears (e.g. position reached top 3), and **respects `dismissed` for 30 days** (no zombie opportunities). Related queries are clustered into one opportunity's evidence (same target page + shared head term) so the brief says "this topic, these 6 phrasings" instead of 6 rows.

**Tests:** pure fixture-seeded unit tests per detector (boundary positions, threshold edges, dismissal persistence, auto-resolve), command idempotency.

**Done when:** prod `search:mine` after backfill produces a plausible opportunity list Bryan recognizes as true (sanity-check against the GSC UI).

---

## Phase 4 — The demand loop: pipeline integration

**Goal:** opportunities actually steer the daily content — the game-changing phase; everything before it is supply lines.

**New/changed:**
- `app/Console/Commands/BuildSearchBrief.php` (`search:brief {--write}`) — renders **`daily-drop/seo-brief.md`**: generated-date + data-through-date header, "how to use this" preamble for agents, then capped sections — 3 review candidates (query cluster, impressions, current best position/page, *"can we add price-intel value?"* checkbox), 2 tip topics + 2 news angles (from `rising`/`content_gap`), 1 refresh candidate (from `decay`/`striking_distance`), and an **avoid list** (topics we'd cannibalize). Deterministic output, ≤150 lines.
- `/morning` skill: new early step — `ssh prod php artisan search:brief` → write `daily-drop/seo-brief.md` locally → include in the day's commit/push (the overnight cloud agent reads it from the repo). Also surface top-3 opportunities + any unindexed-post alerts in the checklist (`search:status --compact` over SSH).
- `/drop-research`, `/drop-tip`, `/drop-news` skills + `daily-drop/CLOUD-AGENT.md`: **consult `seo-brief.md` first** — prefer demand-backed candidates *that pass the existing value gate*; explicitly fall back to current editorial judgment when the brief is missing or stale (>3 days old — say so in research.md). The brief re-ranks; it never overrules quality rules.
- **`target_query`** — nullable column on `seo_meta`; optional `Target query:` line parsed by `bin/daily-drop-build.php` (non-breaking) → carried in `output.json` → mapped by `posts:import`. This makes every post's intent measurable (Phase 6 grades it).
- `/drop-refresh` skill (new, **Sundays** — the review-only day): take the top `decay`/`striking_distance` opportunity, update the post honestly (current price context via PriceIntel, expanded sections answering the cluster's related queries, internal links to/from relevant posts), fix `ctr_fix` titles truthfully, mark the opportunity `planned`→`done`. The save triggers Phase 2 pings + sitemap `lastmod` automatically.

**Tests:** brief renders from seeded opportunities (golden-file-ish assertions), build script accepts + passes `target_query` (extend `DailyDropBuildScriptTest`), importer maps it (extend `ImportDropPostsCommandTest`).

**Done when:** a full cycle is observed — brief committed by /morning → overnight PR's `research.md` cites a brief candidate → post published → pings logged → indexed → its target query shows in `search_query_days`.

---

## Phase 5 — Visibility: `/admin/seo`

**Goal:** Bryan sees the whole system on one page. (After Phase 4 on purpose — the loop drives traffic; the dashboard just watches it.)

**New files:** `app/Http/Controllers/Admin/SeoController.php`, `resources/js/Pages/Admin/Seo/Index.jsx`, routes under the admin middleware group, nav item in `AuthenticatedLayout.jsx`, `tests/Feature/Admin/SeoAdminTest.php` (mirrors `Admin/PricesAdminTest` patterns).

**Panels:** 90-day clicks/impressions trend (Google web + Discover + Bing lines — inline SVG, same approach as the price-history sparkline, **no chart dependency**); opportunities table (filter by kind/status, dismiss / mark planned/done — actions POST back and update `search_opportunities`); index coverage (unindexed posts, days-since-publish, last verdict, manual re-ping button); top movers week-over-week (best/worst Δclicks per post); recent `search_submissions` log. Compact SEO card on the admin dashboard (open opportunities count, unindexed count).

**Done when:** every number on the page reconciles with the GSC/Bing UIs and actions round-trip.

---

## Phase 6 — Frontier menu (à la carte, post-core)

Ordered by expected impact-per-effort; each is a half-to-one-session item:

1. **Review-snippet rich results** — `Product` + `Review` JSON-LD (author rating from our verdicts, price from PriceIntel with honest `priceValidUntil`) on review posts → star-rated SERP listings; measured via the `searchAppearance` dimension we already sync. Biggest realistic CTR lever on the menu.
2. **Brief attribution** — 28 days after publish, compare `seo_meta.target_query` vs the post's actual top queries; grade briefs (hit/miss/sideways) on `/admin/seo`; feed systematic misses back into `CLOUD-AGENT.md` guidance. Makes the loop *self-correcting*, not just closed.
3. **AI referral telemetry** — middleware counting referrers from `chatgpt.com`, `perplexity.ai`, `copilot.microsoft.com`, `gemini.google.com` into a daily counter table (counts only, no PII) + dashboard panel — measures the IndexNow/Bing AI dividend.
4. **Hourly launch telemetry** — `HOUR` dimension (10-day window) on new posts: time-to-first-impression by publish hour → tune the publish time of day for news.
5. **Google Trends adapter** — when the alpha application (Phase 0.7) is granted: `TrendsService` behind `services.trends.enabled`, rising-gadget detection joins the `rising` detector for Mon/Thu news selection.
6. **Internal-link suggestions** — for each open opportunity, LIKE-scan other posts' bodies for the query phrase → "link from post X" suggestions in refresh briefs (highest-leverage on-site SEO for small sites, near-zero cost).
7. **Bing volume enrichment in admin** — `GetKeyword`/`GetRelatedKeywords` on opportunity detail view (budget-note: free, but wrap in the same defensive no-op).

---

## Success metrics

**Leading (weeks):** % of new posts indexed by Google ≤72h of publish (inspection data); IndexNow 200-rate; % of posts carrying `target_query`; opportunities actioned/week.
**Lagging (months):** 28-day organic clicks trend (Google + Bing, from `search_site_days`); count of queries ranking ≤10; striking-distance conversions (4–15 → ≤3 within 28 days of a refresh); CTR delta on `ctr_fix` posts; Discover impressions appearing at all; AI-referral counts (P6.3).
**Review cadence:** monthly dashboard review; threshold retune once ~90 days of post-launch data exists.

## Risks & mitigations

| Risk | Mitigation |
|---|---|
| Young site = sparse data → noisy opportunities | Honesty gates (min impressions/clicks per detector), Bing volume corroboration, brief explicitly falls back to editorial judgment |
| GSC finalized data lags ~2–3 days | `dataState=all` + 5-day re-pull window; the loop is daily-cadence, not realtime — lag is immaterial |
| Service-account key on shared hosting | Outside webroot, `chmod 600`, gitignored, rotate on suspicion; scope limited to `webmasters` |
| Ping/API failures during a save | Inline pings are try/catch + 3s timeout, logged, never block; scheduler retries next day |
| Schedule congestion (hourly cron, minute :00 only) | New tasks at 07:00 / 08:00 / 09:00 UTC — slots verified free in `routes/console.php` |
| sqlite test suite | Schema-builder types only; services no-op without creds so tests never call out; `Http::fake` everywhere |
| `route:cache` on deploy | New routes are plain GETs + admin group — covered by existing deploy verification habit |
| Policy drift (Google updates spam rules) | Guardrails section is the contract; re-check `developers.google.com/search/docs/essentials/spam-policies` quarterly |
| Trends API never granted | Everything else is independent; detector works on GSC `rising` alone until then |

## Reference — key endpoints

```
# Google (Bearer token from google/auth, scope https://www.googleapis.com/auth/webmasters)
POST https://searchconsole.googleapis.com/webmasters/v3/sites/{property}/searchAnalytics/query
PUT  https://searchconsole.googleapis.com/webmasters/v3/sites/{property}/sitemaps/{feedpath}
POST https://searchconsole.googleapis.com/v1/urlInspection/index:inspect

# Bing (apikey query param)
GET https://ssl.bing.com/webmaster/api.svc/json/GetRankAndTrafficStats?apikey=…&siteUrl=…
GET https://ssl.bing.com/webmaster/api.svc/json/GetQueryStats?apikey=…&siteUrl=…
GET https://ssl.bing.com/webmaster/api.svc/json/GetKeyword?apikey=…&q=…&country=us&language=en-US&startDate=…&endDate=…

# IndexNow (fans out to Bing, Yandex, Seznam, Naver, Yep)
POST https://api.indexnow.org/indexnow   {host, key, keyLocation, urlList[]}
```

Docs: [GSC API limits](https://developers.google.com/webmaster-tools/limits) · [searchanalytics.query](https://developers.google.com/webmaster-tools/v1/searchanalytics/query) · [hourly-data announcement](https://developers.google.com/search/blog/2025/04/san-hourly-data) · [Bing Webmaster API](https://learn.microsoft.com/en-us/bingwebmaster/) · [IndexNow](https://www.indexnow.org/documentation) · [Trends API alpha](https://developers.google.com/search/apis/trends) · [Spam policies](https://developers.google.com/search/docs/essentials/spam-policies)
