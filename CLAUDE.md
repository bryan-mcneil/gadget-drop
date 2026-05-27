# GadgetDrop — Claude Code Guide

## Project overview
Amazon affiliate site focused on tech & gadgets. Daily posts surface curated products with affiliate links. Goal: organic Google traffic → affiliate commissions.

- **Domain**: gadgetdrop.tech (registered on Hostinger)
- **Amazon affiliate tag**: `gadgetdroptec-20` (stored in `.env` as `AMAZON_AFFILIATE_TAG`)
- The tag is automatically appended to every `/out/{product}` redirect — never hard-code it in product URLs

## Tech stack
- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: React 19 + Inertia.js v2
- **Styling**: Tailwind CSS v4
- **Build**: Vite 8
- **Database**: MySQL 8.4 (local), MySQL on Hostinger (production)
- **Auth**: Laravel Breeze

## Local dev setup
1. Start MySQL: `Start-Service MySQL84` (Admin PowerShell)
2. Start dev server: `npm run dev` (in `C:\Users\BryanMcNeil\Herd\gadget-drop`)
3. Site runs at: `http://gadget-drop.test` (via Laravel Herd)
4. Stop MySQL when done: `Stop-Service MySQL84`

## Database
- **Local**: MySQL 8.4 · database `gadget_drop` · user `root` / `root`
- **Driver**: mysql (NOT sqlsrv — that was a different environment)
- Migrations: `php artisan migrate`

## Key directories
```
app/Http/Controllers/Admin/     — admin CRUD controllers
app/Http/Controllers/           — PublicController, SitemapController
app/Models/                     — Post, Product, Category, Tag, SeoMeta, AffiliateClick
resources/js/Pages/Admin/       — React admin pages
resources/js/Pages/Public/      — React public pages
resources/js/Layouts/           — AuthenticatedLayout, PublicLayout
.claude/commands/               — expert skill files
```

## Expert skills (invoke with /skill-name)
| Skill | Purpose |
|---|---|
| `/research` | Find today's trending tech products worth covering |
| `/write-post` | Draft a full publish-ready post for a product |
| `/seo-review` | Audit a post for first-page Google ranking |
| `/backend-review` | Laravel security and best practices audit |
| `/frontend-review` | React/Tailwind UX and performance review |
| `/test` | Generate PHPUnit feature tests |

## Typical daily workflow
1. `/research` — find today's product
2. `/write-post` — draft the post content
3. Log into `/admin` → create post → attach products, categories, tags
4. `/seo-review` — review and tweak before publishing
5. Publish

## Affiliate click tracking
All Amazon links go through `/out/{product}?post={id}` which logs a click then redirects. Never link directly to Amazon in the post body — always use this route.

## SEO
- `SeoMeta` model is 1:1 with `Post` — edit via the post form's SEO panel
- Sitemap auto-generated at `/sitemap.xml`
- JSON-LD structured data: TODO (next feature)

## Amazon Associates compliance
- Affiliate disclosure is rendered on every public page automatically via `PublicLayout`
- Product links must use `rel="nofollow sponsored"`

## Deployment (Hostinger)
- PHP 8.3+, MySQL 8, Nginx
- Run: `composer install --no-dev --optimize-autoloader`
- Run: `php artisan migrate --force && php artisan config:cache && php artisan route:cache`
- Pre-build assets locally: `npm run build` → commit `/public/build`
- Nginx `root` → `/public`, `try_files $uri $uri/ /index.php?$query_string`

## Notes
- IDE shows Intelephense P1009 "Undefined type" errors on route files — these are false positives, the code runs fine. Install Laravel Intelephense stubs to fix.
- npm installs require `--legacy-peer-deps` (Vite 8 / @vitejs/plugin-react peer dep mismatch)
- Composer installs require `--prefer-source` to avoid Windows Search Indexer locking zip files
