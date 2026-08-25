# GadgetDrop — Claude Code Guide

## Project overview
Amazon affiliate site focused on tech & gadgets. Daily posts surface curated products with affiliate links. Goal: organic Google traffic → affiliate commissions.

- **Domain**: gadgetdrop.tech (registered on Hostinger)
- **Amazon affiliate tag**: `gadgetdroptec-20` (stored in `.env` as `AMAZON_AFFILIATE_TAG`)
- The tag is automatically appended to every `/out/{product}` redirect — never hard-code it in product URLs

## Tech stack
This is a **hybrid** app — the public site and the admin area use different rendering stacks on purpose.

- **Backend**: Laravel 13.11.0, PHP 8.4.21
- **Public frontend**: Blade (SSR) + **Livewire 4** + **Alpine.js** — server-rendered HTML for SEO (crawlers see the H1, meta, and JSON-LD with JS off)
- **Admin frontend**: **React 19 + Inertia.js v2** (unchanged; `/admin` only) — this hybrid is permanent
- **Styling**: **Tailwind CSS v3** (v3.2.1, via PostCSS + `tailwind.config.js`). The `@tailwindcss/vite` v4 plugin is in devDeps but **NOT wired in** — do not switch to v4.
- **Markdown**: `league/commonmark` (server-side, renders `post.body`)
- **Build**: Vite 8 (dual entries — see below)
- **Database**: MySQL 8.4 (local), MySQL on Hostinger (production); `sqlite :memory:` for tests
- **Auth**: Laravel Breeze (single admin account; `register` route is intentionally disabled)

> **History:** the public frontend was migrated off Inertia/React to Blade+Livewire+Alpine (the `feature/php-ssr-seo` → `feature/blade-livewire-migration` work) to get true server-side rendering for SEO. Admin stays on Inertia/React.

## Local dev setup
1. Start MySQL: `Start-Service MySQL84` (Admin PowerShell)
2. Start dev server: `npm run dev` (in `C:\Users\BryanMcNeil\Herd\gadget-drop`)
3. Site runs at: `http://gadget-drop.test` (via Laravel Herd)
4. Stop MySQL when done: `Stop-Service MySQL84`

> When testing the **production build** instead of the dev server, run `npm run build` (assets land in `public/build`). There is no `hot` file in that mode, so Herd serves the built assets.

## Database
- **Local**: MySQL 8.4 · database `gadget_drop` · user `root` / `root`
- **Driver**: mysql (NOT sqlsrv — that was a different environment)
- Migrations: `php artisan migrate`
- MySQL-only migrations (e.g. `ALTER ... MODIFY COLUMN ... ENUM`) must be guarded with `if (DB::getDriverName() !== 'mysql') return;` so the sqlite test suite doesn't break.

## Key directories
```
app/Http/Controllers/Admin/     — admin CRUD controllers (Inertia/React responses)
app/Http/Controllers/           — PublicController, ToolController, SubscriberController, SitemapController (return Blade views)
app/Livewire/                   — JoinTheDrop, Unsubscribe (class-based Livewire components)
app/Support/                    — NavigationData (memoized nav data), ArticleBody (markdown → sections)
app/Models/                     — Post, Product, Category, Tag, SeoMeta, AffiliateClick

resources/views/layouts/public.blade.php   — PUBLIC layout (Blade/Livewire/Alpine)
resources/views/app.blade.php              — ADMIN layout (Inertia root)
resources/views/public/                    — public pages: home, show, category, author, news, search, static (about/privacy/terms/contact/cookies), unsubscribe, tools/*
resources/views/components/                — Blade components (x-public.header, x-public.footer, x-post-card, x-share-bar, x-article-body, x-cookie-consent, x-ad-unit, x-tools.*, x-news.*, …)
resources/views/livewire/                  — Livewire component views
resources/views/errors/404.blade.php       — 404 (Blade)

resources/js/app.js             — PUBLIC entry: imports app.css, registers Alpine components on `alpine:init`
resources/js/lib/               — heavy tool engines, dynamically imported (docx-pdf.js = DOCX→PDF)
resources/js/app.jsx            — ADMIN entry: React + Inertia
resources/js/Pages/Admin/       — React admin pages
resources/js/Layouts/AuthenticatedLayout.jsx — admin layout
resources/css/app.css           — Tailwind directives + @font-face + custom CSS

.claude/commands/               — expert skill files
```

## Frontend architecture (read before touching the public site)

- **Vite entries** in `vite.config.js`: `resources/js/app.js` (public/Alpine core, every page) + `resources/js/tools.js` (free-tool Alpine components, loaded only on `/tools/*` via a `request()->routeIs('tools.*')` check in `layouts/public.blade.php`) + `resources/js/app.jsx` (admin/React) — plus `resources/css/app.css`. `app.js`/`app.jsx` `import '../css/app.css'` (tools.js does NOT — app.js already loads it on tool pages), so global CSS (incl. `@font-face`) applies everywhere. After any change, smoke-test BOTH `/` and `/admin`. Put tool Alpine components in `tools.js`, core/public ones (header, carousel, share, cookie consent) in `app.js`; both register on `alpine:init`.
- **Heavy tool dependencies go in `resources/js/lib/*` behind a dynamic `import()`** so one tool's libraries never load on the other tool pages (terser → minifier, cropperjs → image editor, `lib/docx-pdf.js` bundling mammoth + jsPDF → DOCX→PDF). **Do NOT give those libraries `manualChunks` entries** — naming them made Rollup park Vite's preload helper inside the jsPDF chunk, which turned 400 kB into a *static* import of the `tools.js` entry (every tool page paid for it). After touching a lazy tool dep, verify in `public/build/manifest.json` that `resources/js/tools.js` → `imports` still lists only the rolldown runtime + preload helper, and that the library sits under `dynamicImports`.
- **Livewire owns Alpine.** Livewire bundles and boots Alpine globally. Register custom Alpine components in `app.js` on the **`alpine:init`** event. **NEVER call `Alpine.start()`** (double-Alpine).
- **Navigation data** comes from one source: `App\Support\NavigationData::get()` (memoized). It feeds both the Inertia middleware (`HandleInertiaRequests`, for admin) and a View Composer bound to `layouts.public` (for the public site). "Has posts" filters use `whereHas('posts', …)`, **not** `having('posts_count', …)` (the latter errors on sqlite tests).
- **Markdown** for `post.body` is rendered server-side by `App\Support\ArticleBody::sections()` (CommonMark, `html_input => 'escape'`), rendered via `<x-article-body>`. **Structure-aware image placement (Plan 10.1):** `image_1/2/3` are injected only **between two paragraph blocks** of running text (never adjacent to a heading/divider/list/blockquote, never at the doc end), distributed ~30/55/80% of cumulative text with a second-tier "after the final paragraph of a middle section" fallback — it no longer cuts by raw block count. Contain-fit images render on a tinted **figure mat**, cover-fit go full-column; optional `image_{1,2,3}_caption` columns render as a centred `<figcaption>`. `sections()` also stamps slug ids on H2s (shared `headingSlug()`) for the post's "On this page" sidebar nav. The cache key is versioned (`article-body.v3.`, captions in the hash) — **bump the version on any placement/markup change** so a deploy re-renders cleanly with no manual flush. The drop-cap CSS in `app.css` keys on `.post-body > div:first-child .prose p:first-child::first-letter` — preserve that DOM structure.
- **Post anatomy (Plan 10.2):** a full-bleed dark **intel band** (`<x-intel-strip>`) holds the type/category eyebrow, H1, byline, and a one-line price strip (current tracked price · mini sparkline · verdict chip · checked-at) — a summary, **not** a CTA. The hero image sits BELOW the band on the light surface (LCP preload untouched); prose is `max-w-[68ch] prose-lg`; the three pre-body price widgets (`<x-price-history>`, `<x-buy-or-wait-strip>`, watch signup) disperse to the end-zone around the single product-card CTA. The sidebar is `lg:sticky` with "On this page" + Related Products + one capped list. A post with `recap_video_path` renders the hero slot as a poster-first 16:9 recap `<video>` instead (`<x-hero-recap>`, plan 10.7): poster = the hero WebP (still the preloaded LCP), `muted playsinline preload="none"`, autoplay exactly once via the `heroRecap` Alpine component (reduced-motion → poster + play button), VideoObject JSON-LD emitted server-side, files self-hosted under `storage/app/public/recaps/` (admin uploads via `admin.videos.store`).
- **SEO is server-side.** Controllers call `view()->share('serverMeta', [...])` and (home/post) `view()->share('serverJsonLd', ...)`; `layouts/public.blade.php` emits `<title>`, description, canonical, OG tags, and the JSON-LD block. Every public method sets a per-page `serverMeta` array.
- **Affiliate disclosure** + cookie consent render on every public page via `layouts/public.blade.php` (`<x-affiliate-disclosure>` / `<x-cookie-consent>`). All Amazon links use `rel="nofollow sponsored"` (or `nofollow noopener`) and route through `route('affiliate.redirect', …)` → `/out/{product}` — never link to Amazon directly in the body.
- **Fonts (no FOUT):** Figtree is **self-hosted** in `public/fonts/figtree/` (committed). `@font-face` rules live in `app.css` (render-blocking) pointing at `/fonts/figtree/...` with `font-display: optional`; the `.woff2` files are `<link rel="preload">`ed in `layouts/public.blade.php`. Do not reintroduce the external `fonts.bunny.net` origin or an async font stylesheet (`onload="this.rel='stylesheet'"`) — both hurt LCP / cause a bold-flash swap.
- **Performance:** public listing queries rely on the `posts (status, published_at)` / `(status, view_count)` indexes (migration `2026_06_08_000001`). `NavigationData::get()`, `ArticleBody::sections()`, and the sitemap are cached on the **database** cache store (no Redis on Hostinger); `Post` model `saved`/`deleted` events call `NavigationData::flush()` to bust them — the per-view `view_count` increment is excluded so page views don't nuke the cache. Public images render through `<x-responsive-image>` / `<x-adaptive-image>`, which emit `<picture>` WebP `srcset` from variants that `App\Support\ImageVariants` generates *alongside* originals (never replacing them) on upload and via `php artisan images:optimize`. Variant sources include **webp uploads** (jpg/jpeg/png/webp); `ImageVariants::isVariant()` keeps generated `-480/-960/-1600.webp` files from ever being treated as sources (no variant-of-variant). Never render a storage image with a raw `<img>`: use `<x-responsive-image>` with `sizes`, `loading="lazy"` (unless above the fold), and `width`/`height`. Static-asset cache lifetimes are set in `public/.htaccess` (build/fonts 1y immutable, images 30d) since those files bypass PHP/`CacheViteAssets`.
- **Instant navigation (`wire:navigate`):** every internal public link carries `wire:navigate` (Livewire swaps the body, SPA feel, SSR HTML for crawlers stays intact). Hard exclusions, do NOT add it to: `/out/{product}` affiliate redirects, `/admin` links, external/mailto links, and any link INTO `/tools/*` (tools.js only loads on tool pages and registers its Alpine components on `alpine:init`, which never re-fires on a navigate visit). Do not use `wire:navigate.hover` prefetch: each hover would server-render the page and bump `view_count`. Pagination anchors get the attribute via the published view in `resources/views/vendor/pagination/tailwind.blade.php`. Alpine components in `app.js` that attach window/document listeners or timers MUST clean up in `destroy()` (see `heroCarousel`, `readingProgress`) or they leak once per navigation.
- **Motion respects `prefers-reduced-motion` (Plan 10 budget) — forward convention.** JS animations bail in code (`sparklineDraw`, `countUp` early-return via `matchMedia('(prefers-reduced-motion: reduce)')`); the **card system** (`post-card`, `author-post-card`, home Top Picks featured + compact) guards its hover lift + image zoom + arrow nudge + rail sweep with `motion-reduce:` variants, and the Drop-Price keyframes are disabled in `app.css`'s reduced-motion block. **Any new hover lift/zoom or infinite `animate-*` MUST add a `motion-reduce:` guard** (a non-motion cue — shadow/color — may stay). Not yet swept: some pre-existing hover zooms outside the refreshed cards (`news`/`search` listing thumbs, home category strip, `drop-price` archive cards, `worth-it-vote`) — bring them behind the guard when you next touch them (`tag` was swept when its card got the same-layer zoom fix). **Card hover-zoom structure (cards with a white bottom fade over the image — `post-card`, the `tag` listing card):** the zoom transform lives on an overscan div containing the image AND the fade in ONE compositor layer (see the comment in `post-card.blade.php`) — a fade painted as a sibling over an animating image lands on its own layer and can misregister, flashing a dark 1px line at the image bottom. On those cards, never move the scale back onto the `<img>` or hoist the fade out of the animated div; fade-less zooms (Top Picks, `news`/`search` thumbs) scale the `<img>` directly and that's fine. Contrast: secondary metadata on **light** surfaces should use `text-gray-500` (≈4.8:1 on white, AA-pass), not `text-gray-400` (≈2.5:1, fails); `text-gray-400` stays fine on the **dark** bands and for deliberate low-emphasis boilerplate (affiliate/commission lines, "Last updated" stamps).
- **AdSense is OFF** behind `services.adsense.enabled` (`ADSENSE_ENABLED` env, default false). The layout script tag, resource hints, and `<x-ad-unit>` all no-op while disabled; usages stay in place. If re-enabled, ad slots on pages reached via `wire:navigate` must be re-pushed from a `livewire:navigated` listener (DOMContentLoaded does not re-fire on swaps).

## Build / Tailwind gotchas
- `npm run build` after editing CSS, JS, **or Blade classes** (Tailwind purges based on content scan).
- **Tailwind scans `./storage/framework/views/*.php`** (compiled Blade) in addition to `resources/views`. Stale compiled views can keep old classes alive or mask changes — if a class seems missing/stale after a build, run `php artisan view:clear` then rebuild.
- Content globs already include `resources/views/**/*.blade.php`, `app/View/Components/**/*.php`, `app/Livewire/**/*.php`, `resources/js/**/*.{js,jsx}`. New dynamic color/utility classes only used via Alpine `:class` strings must appear literally somewhere scannable or they'll be purged.

## Responsive / mobile conventions (hard-won)
- **Flex/grid overflow:** grid & flex items default to `min-width: auto` and won't shrink below their content. A wide image or a `truncate`/nowrap child then forces the track past the viewport → horizontal scroll. Fix: add `min-w-0` to the flex/grid item and `max-w-full` to images.
- **`md` breakpoint cramming:** the public header switches to full desktop nav at `md` (768px). Keep widths shrinkable in the `md`→`lg` range (e.g. search box is `w-44 lg:w-60`) so it fits on ~768px tablets (iPad Mini).
- **Hero carousel:** arrows are `hidden lg:flex` (they overlapped text on mobile/tablet); finger-swipe is handled in the `heroCarousel` Alpine component (horizontal-only, 40px threshold, passive listeners).
- **Diagnosing horizontal scroll** — paste in the browser console; the outermost logged element (ignoring intentional `overflow-x-auto` carousels and `overflow-hidden` decorations) is the culprit:
  ```js
  const w = document.documentElement.clientWidth;
  [...document.querySelectorAll('*')]
    .filter(el => el.getBoundingClientRect().right > w + 1)
    .forEach(el => console.log(Math.round(el.getBoundingClientRect().right - w) + 'px over →', el));
  ```

## Blade + Alpine gotchas
- `@error="..."` collides with Blade's `@error` directive → use **`x-on:error`** for Alpine error handlers. (`@click`, `@input`, etc. are fine; Livewire's `@error('field')` is correct/intentional.)
- **FOUC:** elements driven only by `:class`/`:style` flash their raw state before Alpine boots. Give a static initial `style`/`class` that Alpine overrides, and use `x-cloak` for show/hide (`[x-cloak]{display:none}` is in `app.css`).
- **Admin → public links:** from the Inertia/React admin, link to public (Blade) routes with a plain `<a href>`, never Inertia `<Link>` (it XHR-loads the Blade page into Inertia's debug modal).
- **cropperjs** is pinned to **v1** (`^1.6.2`) — the image-cropper tool uses the v1 API; v2's element API is incompatible.
- **Livewire 4** defaults to single-file components; this project uses **class-based** components (`app/Livewire/*` + views in `resources/views/livewire/*`).
- **No Blade directives *inside* a `<x-component>` tag.** The component compiler parses `<x-…>` tags *before* directives, so an `@if … @endif` between a component's attributes breaks compilation — the component never renders and its attributes leak into the page as raw text (this is what once made every `<x-adaptive-image>` hero image vanish). Use **bound `:` attributes** for conditionals (`:width="$width"`, `:style="$cond ? '…' : null"`) — the attribute bag drops `null`/`false` values. Wrapping a whole `<x-…>` tag in `@if`/`@endif` is fine; directives *between its attributes* are not.

## Expert skills (invoke with /skill-name)
| Skill | Purpose |
|---|---|
| `/daily-drop` | Pipeline status router: weekly cadence table + which step to run next |
| `/drop-research` | Step 1: find today's product (1 primary + 1 backup), save `daily-drop/research.md` |
| `/drop-write N` | Step 2: write the day's ONE review (byline Bryan McNeil), save `daily-drop/product-N.md` |
| `/drop-tip` | Tue/Sat: write a tech tip (600–1000 words, SOURCE_URL required) → `daily-drop/tip-1.md` |
| `/drop-news` | Mon/Thu: write buyer-focused news (600–900 words, `## Buy or Wait?` required) → `daily-drop/news-1.md` |
| `/drop-assemble` | Step 3: build `daily-drop/output.json` via `php bin/daily-drop-build.php` |
| `/drop-refresh` | Sun: refresh the top decaying/striking-distance post from Search Intel opportunities (honest update, local + DB) |
| `/morning` | Bryan's one daily command: merge the cloud agent's PR, import drafts into PROD, QA gate, checklist |
| `/seo-review` | Audit a post for first-page Google ranking |
| `/backend-review` | Laravel security and best practices audit |
| `/implement-phase` | Execute exactly ONE phase of a `docs/plans/` plan: verify ground truth, implement, test, gd-code-reviewer review, prepare commit, stop for approval |
| `/plan-status` | Status board across all `docs/plans/` feature plans + recommended next action |
| `/gd-health` | Monthly + post-deploy health sweep: suite, budgets, snapshot coverage, feature surfaces, prod spot checks |

Editorial + AdSense rules per type: `CONTENT-GUIDELINES.md`. Daily routine + prod runbook: `docs/OPERATIONS.md`. Search Intel (GSC/Bing/IndexNow) plan: `search-intel.md`; one-time setup + bring-up: `docs/SEARCH-INTEL-SETUP.md`.

Feature implementation plans live in `docs/plans/` (index: `docs/plans/README.md`) — one reviewable phase at a time via `/implement-phase`. Project subagents: `gd-code-reviewer` (pre-commit diff review against plan + invariants, model: opus) and `gd-test-engineer` (test authoring/repair under this repo's testing constraints). Both keep project-scoped agent memory.

The pipeline passes state through files (`daily-drop/research.md` → `daily-drop/{product,tip,news}-*.md` → `bin/daily-drop-build.php` → `daily-drop/output.json` → `php artisan posts:import`), so each step can run in a fresh session on a cheaper model (use `/handoff` between steps) or unattended by the scheduled cloud agent (`daily-drop/CLOUD-AGENT.md`). The model never writes the final JSON; the build script parses, validates per post type, and assembles it. `daily-drop/` is tracked in git on purpose — the cloud agent delivers content via `drop/YYYY-MM-DD` PRs.

## Typical daily workflow
Cadence: 1 review every day + tech tip Tue/Sat + tech news Mon/Thu (11 posts/week).

1. Overnight, the scheduled cloud agent runs research → write → assemble and opens a PR `drop/YYYY-MM-DD` (instructions: `daily-drop/CLOUD-AGENT.md`).
2. `/morning` — merges the PR, re-validates, dry-runs the import, pushes main, then imports on the server (`php artisan posts:import daily-drop/output.json` over SSH). Fallback when SSH isn't handy: paste `daily-drop/output.json` into the prod `/admin/daily-drop` Import page (same importer, accepts all three types). No PR? `/morning` offers to run the pipeline locally.
3. Review the 1–2 drafts, add images, publish.
4. `/admin/prices` — quick pass on the stalest products: type the new price (records a snapshot) or hit "Unchanged" (records a same-price check snapshot — max one per product per day — and refreshes the checked-at stamp). Keeps every "Price checked {date}" label and the `/deals` feed honest.

## Affiliate click tracking
All Amazon links go through `/out/{product}?post={id}` which logs an `AffiliateClick` then redirects (tag appended automatically). Never link directly to Amazon in the post body — always use `route('affiliate.redirect', …)`. (The current post template goes further: NO Amazon links in the body at all — the `<x-product-card>` above the article is the single affiliate CTA; `bin/daily-drop-build.php` warns on in-body Amazon links.)

## Price intelligence (the site's unique value layer)
- **Snapshots**: `product_price_snapshots` (price, `source`: manual|canopy|pa_api|drop_price|import|initial). `App\Observers\ProductObserver` appends a snapshot on any price change and stamps `products.price_checked_at`; a save that only refreshes `price_checked_at` (admin "Unchanged"/same-price submit, API refresh returning the same price) appends a same-price check snapshot, max one per product per day, so checks stay durable (the Truth Report observation gate can only trust snapshot rows). Commands set `ProductObserver::$source` first so provenance is recorded.
- **Math**: `App\Support\PriceIntel` (db-cached 6h, defensive like `DropPrice`) — 30/90-day low/avg/high over a carry-forward daily series, verdict tiers (lowest/good/typical/elevated). **Honesty gates**: stats + verdicts are null until ≥2 snapshots spanning ≥14 days (the span carries the honesty — snapshots only record price *changes*, so requiring more points would keep one-drop products off `/deals` indefinitely); a flat price is "typical", never "lowest". `PriceIntel::flush($id)` busts the per-product key + the `/deals` feed.
- **Surfaces**: `<x-price-history>` under each product card on review pages ("Price checked {date}" always; sparkline + verdict once gated stats exist — no extra CTA, single-CTA rule holds); `/deals` (indexable, in sitemap/nav) lists products ≥5% below their tracked 90-day average with a published review; the Drop Price game locks one real price per day (backfill source).
- **Refresh sources**, in priority order (`prices:refresh`, scheduled 06:00): PA-API via `AmazonProductService` when configured → **Canopy API** (`CanopyApiService`, `CANOPY_API_KEY`) on the FREE tier only — hard budget guards (`daily_limit` 3/day + `monthly_budget` 90/mo counted on the db cache) keep it structurally $0; no card on the Canopy account → explicit no-op. Manual backstop: `/admin/prices`. **Never scrape Amazon** (Associates OA violation → account termination).
- Snapshot history was seeded on prod by the one-time `prices:backfill` command (Drop Price puzzle history + initial snapshots), since deleted along with the other consolidation one-offs.
- **Market layer** (wide, uncurated): `market_products` + `market_price_snapshots` hold every ASIN ever imported by `market:import {file}` — a source-agnostic CSV/XLSX importer (openspout; contract of record: `docs/MARKET-IMPORT.md`; the repo itself never scrapes). Matching `products.asin` rows get the price pushed through `ProductObserver` with `source='import'`; each run writes a JSON report to `storage/app/market/`. Thresholds: `config/market.php`. A market row can be **promoted** into the curated catalog from `/admin/market-products/{id}/edit` (`MarketPromotionService`): creates the `products` row prefilled and copies the ASIN's market snapshot history into `product_price_snapshots` with `source='market'` at the original observation dates (PriceIntel gates open immediately); insert-with-history, never a move — the market row stays and future imports keep the promoted price current by ASIN.

### Live price compare (Claude + web search) — plan 11
On review pages, `@livewire('live-price-compare')` sits under `<x-price-history>` and, on click, asks Claude (`claude-sonnet-5` + the `web_search_20250305` server tool) what the product costs at other US retailers. `App\Services\PriceCompareService` makes the call and returns the raw payload or **null on any failure, never an exception**; `App\Support\PriceComparison` (pure, no facades) does all the filtering, ranking, and claim-writing. Off by default behind `PRICE_COMPARE_ENABLED`; config in `config/price-compare.php`.

Five things here are load-bearing. Do not "simplify" any of them:
- **Amazon's price never comes from search** (Associates §2(b)). The Amazon row is our own `products.price`. The tool gets an `allowed_domains` **whitelist** of first-party retailers (never a blocklist, never `amazon.com`), and `PriceComparison` re-checks every returned host against that same list. `allowed_domains` and `blocked_domains` are mutually exclusive — sending both is a 400.
- **Build the `tools` payload from typed SDK objects** (`WebSearchTool20250305::with(...)`), not a raw array. The SDK forwards raw arrays to the wire *verbatim and unvalidated*, so a camelCase or misspelled key ships unrecognised and the search runs with **no whitelist at all**.
- **`timeout` and `maxRetries: 0` must be passed on the `create()` call, not the `Client` constructor.** Constructor values are silently overridden per request (`SdkParams::parseRequest()` mints a fresh `RequestOptions` whose required properties carry the 600s/2-retry defaults). There is no `queue:work` on prod, so this runs inside the reader's request and must fail before LiteSpeed does. Guard test: `test_a_transport_failure_is_not_retried`.
- **Nothing is persisted** (§6(y)): third-party prices live only in a 24h cache entry. No table, no snapshots, no alerts, ever. The cache key fingerprints `products.price` **only** — the raw payload is cached and normalized on read, so a re-check of the same price reuses it while a changed price buys a fresh search.
- **The winner claim is gated on freshness, the table is not.** "X is lowest" renders only when `price_checked_at` is within `fresh_days`; past that the rows still show and PHP names no winner. The error being engineered against is wrongly crowning *Amazon*, which is the one that flatters our own commission.

## SEO
- `SeoMeta` model is 1:1 with `Post` — edit via the post form's SEO panel
- Per-page meta emitted from `serverMeta` (title, description, canonical, OG) on every public route
- Sitemap auto-generated at `/sitemap.xml`
- JSON-LD structured data: emitted server-side on home + post pages (`serverJsonLd`)
- **Slug renames auto-301**: changing a published post's slug records the old slug in `post_slug_redirects` (`PostObserver`), and `/posts/{old}` 301s to the current URL via the `posts.show` `missing()` fallback. Never hand-write `Route::permanentRedirect` for `/posts/*` — the `/posts/{post:slug}` catch-all is registered first and would shadow it (that's how two dead redirects shipped in July 2026).

## Tests
- `php artisan test` — **suite is fully green (560 tests).** Coverage: `PublicPagesTest` (each public route renders H1/meta), `Livewire/JoinTheDropTest` + `Livewire/ContactFormTest` (honeypot/throttle/mail), `AffiliateRedirectTest`, `Unit/ArticleBodyTest`, `ImageVariantsTest` (webp sources + variant-of-variant guard), `PublicLayoutTest` (AdSense flag off/on, wire:navigate present), `AdsensePrepTest` (tools noindex, single CTA, persona 301s), `ThinPageCleanupTest` + `SitemapTest` (tag/category/post noindex rules, ≥3-post category threshold), `FlagClaimsCommandTest`, `ImportDropPostsCommandTest` (posts:import: all types as drafts, category_map, dry-run) + `DailyDropBuildScriptTest` (build-script exit codes, process-based), `PriceSnapshotTest` + `PriceHistoryWidgetTest` + `DealsPageTest` + `RefreshPricesCommandTest` + `Admin/PricesAdminTest` (the price-intelligence layer), DropPrice suites.
- **DB-wipe guardrails (2026-07-06 incident — do not remove any of them).** A locally cached config (`php artisan optimize`/`config:cache`) makes Laravel ignore every `<env>` override in phpunit.xml, so tests once booted as env `local` on the real MySQL DB and RefreshDatabase migrate:fresh'd it. Three layers now prevent this: (1) `tests/bootstrap.php` (wired via phpunit.xml `bootstrap=`) deletes a stale `bootstrap/cache/config.php` before PHPUnit boots; (2) `AppServiceProvider::boot()` calls `DB::prohibitDestructiveCommands()` — `migrate:fresh/refresh/reset/rollback` and `db:wipe` are blocked on every env except `testing` unless `.env` sets `DB_ALLOW_DESTRUCTIVE=true` (config `database.allow_destructive`); (3) `tests/TestCase.php::refreshApplication()` hard-aborts the suite unless it booted as testing + sqlite + `:memory:`. Never leave `optimize`/`config:cache` active in the local checkout — run `php artisan optimize:clear` after any prod-build experiment.
- The sqlite test schema's posts.type CHECK predates `tech_news` (that enum widening is a guarded MySQL-only migration) — tests must not insert/update posts to type `tech_news`; use `tech_tip` to exercise non-article branches.
- The old Breeze scaffold failures were cleaned up: `AuthenticationTest`/`EmailVerificationTest` now assert `route('admin.dashboard')` (the app's real post-login route); `RegistrationTest` now asserts `/register` is intentionally **disabled** (404) rather than testing the removed feature; the redundant `Feature/ExampleTest` was deleted (the homepage is covered by `PublicPagesTest`).
- `Unit/ArticleBodyTest` is a pure PHPUnit unit test (no Laravel boot) — `ArticleBody::sections()` therefore guards its `Cache::remember` and renders directly if the cache layer isn't bound. Don't add hard facade dependencies to support classes that are unit-tested this way.

## Deployment (Hostinger Business Web Hosting)
- PHP 8.4.19 (production), MySQL 8, **LiteSpeed** (NOT Nginx; verified via response headers). LiteSpeed honors `public/.htaccess`, which is where the static-asset cache headers live.
- Hostinger CDN ("hcdn") fronts the site (Business plan and above). WebP compression and Smart Image Optimization toggles are ON in hPanel, but they do NOT resize oversized originals in practice: responsive variants from `ImageVariants` are the real fix. After a deploy that changes assets/HTML, purge the CDN cache in hPanel (Performance, CDN).
- **Deploy = build locally, commit, then run `bash bin/deploy.sh` on the server over SSH.** The script does: `git pull --ff-only`, `composer install --no-dev --optimize-autoloader`, `migrate --force`, `php artisan optimize` (config + routes + views + events), `images:optimize`. Rollback for cache issues: `php artisan optimize:clear`.
- Pre-build assets locally: `npm run build` then commit `/public/build` (NOT gitignored; git pull delivers code + assets atomically; no File Manager uploads).
- **Scheduler/cron:** one hPanel cron runs `php artisan schedule:run` HOURLY at minute 0 (`0 * * * * /opt/alt/php84/usr/bin/php /home/u746229724/domains/gadgetdrop.tech/public_html/artisan schedule:run`), not every minute, to save shared-hosting CPU. Therefore every task in `routes/console.php` MUST be scheduled at minute `:00` (times are UTC). Current tasks: `dropprice:lock` daily 00:00, `images:optimize` daily 04:00 (self-healing variant backfill), `cache:prune-expired` daily 05:00 (the database cache store never sweeps expired rows it does not re-read), `prices:refresh` daily 06:00 (PA-API → Canopy → no-op; budget-guarded), `newsletter:send` Fridays 14:00.
- Self-hosted fonts live in `public/fonts/figtree/` (committed) — no external font CDN.
- **Livewire** assets are served by Laravel via `@livewireScripts` (v4 serves through a route — no `livewire:publish --assets` needed). Verify `route:cache` doesn't break Livewire's update endpoint (Join the Drop form) after the first cached-routes deploy.

## Notes
- IDE shows Intelephense P1009 "Undefined type" errors on route files — false positives, the code runs fine. Install Laravel Intelephense stubs to fix.
- npm installs require `--legacy-peer-deps` (Vite 8 / @vitejs/plugin-react peer dep mismatch)
- Composer installs require `--prefer-source` to avoid Windows Search Indexer locking zip files
- Commit/push only when explicitly asked. Default working branch is the feature branch; nothing is committed automatically.
