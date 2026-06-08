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
resources/js/app.jsx            — ADMIN entry: React + Inertia
resources/js/Pages/Admin/       — React admin pages
resources/js/Layouts/AuthenticatedLayout.jsx — admin layout
resources/css/app.css           — Tailwind directives + @font-face + custom CSS

.claude/commands/               — expert skill files
```

## Frontend architecture (read before touching the public site)

- **Two Vite entries** in `vite.config.js`: `resources/js/app.js` (public/Alpine) + `resources/js/app.jsx` (admin/React) — plus `resources/css/app.css`. Both JS entries `import '../css/app.css'`, so global CSS (incl. `@font-face`) applies to both areas. After any change, smoke-test BOTH `/` and `/admin`.
- **Livewire owns Alpine.** Livewire bundles and boots Alpine globally. Register custom Alpine components in `app.js` on the **`alpine:init`** event. **NEVER call `Alpine.start()`** (double-Alpine).
- **Navigation data** comes from one source: `App\Support\NavigationData::get()` (memoized). It feeds both the Inertia middleware (`HandleInertiaRequests`, for admin) and a View Composer bound to `layouts.public` (for the public site). "Has posts" filters use `whereHas('posts', …)`, **not** `having('posts_count', …)` (the latter errors on sqlite tests).
- **Markdown** for `post.body` is rendered server-side by `App\Support\ArticleBody::sections()` (CommonMark, `html_input => 'escape'`). It reproduces the old react-markdown thirds-split + `image_1/2/3` injection + custom `<hr>`/`<blockquote>` styling, rendered via `<x-article-body>`. The drop-cap CSS in `app.css` keys on `.post-body > div:first-child .prose p:first-child::first-letter` — preserve that DOM structure.
- **SEO is server-side.** Controllers call `view()->share('serverMeta', [...])` and (home/post) `view()->share('serverJsonLd', ...)`; `layouts/public.blade.php` emits `<title>`, description, canonical, OG tags, and the JSON-LD block. Every public method sets a per-page `serverMeta` array.
- **Affiliate disclosure** + cookie consent render on every public page via `layouts/public.blade.php` (`<x-affiliate-disclosure>` / `<x-cookie-consent>`). All Amazon links use `rel="nofollow sponsored"` (or `nofollow noopener`) and route through `route('affiliate.redirect', …)` → `/out/{product}` — never link to Amazon directly in the body.
- **Fonts (no FOUT):** Figtree `@font-face` rules live in `app.css` (render-blocking) with `font-display: optional`; the `.woff2` files are `<link rel="preload">`ed in both layouts. Do not reintroduce an async font stylesheet (`onload="this.rel='stylesheet'"`) — it caused a bold/flash swap on load.

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

## Expert skills (invoke with /skill-name)
| Skill | Purpose |
|---|---|
| `/research` | Find today's trending tech products worth covering |
| `/write-post` | Draft a full publish-ready post for a product |
| `/seo-review` | Audit a post for first-page Google ranking |
| `/backend-review` | Laravel security and best practices audit |
| `/frontend-review` | Blade/Alpine/Livewire (public) & React (admin) UX + performance review |
| `/test` | Generate PHPUnit feature tests |

## Typical daily workflow
1. `/research` — find today's product
2. `/write-post` — draft the post content
3. Log into `/admin` → create post → attach products, categories, tags
4. `/seo-review` — review and tweak before publishing
5. Publish

## Affiliate click tracking
All Amazon links go through `/out/{product}?post={id}` which logs an `AffiliateClick` then redirects (tag appended automatically). Never link directly to Amazon in the post body — always use `route('affiliate.redirect', …)`.

## SEO
- `SeoMeta` model is 1:1 with `Post` — edit via the post form's SEO panel
- Per-page meta emitted from `serverMeta` (title, description, canonical, OG) on every public route
- Sitemap auto-generated at `/sitemap.xml`
- JSON-LD structured data: emitted server-side on home + post pages (`serverJsonLd`)

## Tests
- `php artisan test`. New migration coverage: `PublicPagesTest` (each public route renders H1/meta), `Livewire/JoinTheDropTest`, `AffiliateRedirectTest`, `Unit/ArticleBodyTest`.
- **Pre-existing Breeze failures (not from our work):** `RegistrationTest` (register route intentionally disabled), `AuthenticationTest`/`EmailVerificationTest` (reference `route('dashboard')`; app uses `admin.dashboard`), `ExampleTest` (no `RefreshDatabase`). Getting these green is a tracked cleanup item.

## Deployment (Hostinger)
- PHP 8.4.19 (production), MySQL 8, Nginx
- Run: `composer install --no-dev --optimize-autoloader`
- Run: `php artisan migrate --force && php artisan config:cache && php artisan route:cache`
- Pre-build assets locally: `npm run build` → commit `/public/build`
- Nginx `root` → `/public`, `try_files $uri $uri/ /index.php?$query_string`
- **Livewire** assets are served by Laravel via `@livewireScripts` (v4 serves through a route — no `livewire:publish --assets` needed). Verify `route:cache` doesn't break Livewire's update endpoint.

## Notes
- IDE shows Intelephense P1009 "Undefined type" errors on route files — false positives, the code runs fine. Install Laravel Intelephense stubs to fix.
- npm installs require `--legacy-peer-deps` (Vite 8 / @vitejs/plugin-react peer dep mismatch)
- Composer installs require `--prefer-source` to avoid Windows Search Indexer locking zip files
- Commit/push only when explicitly asked. Default working branch is the feature branch; nothing is committed automatically.
