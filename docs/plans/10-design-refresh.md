# Plan 10 — Design Refresh: the Price-Truth Instrument

**Goal:** the site reads as one designed thing — the price-intelligence layer becomes the visual signature (verdict chips, sparklines, tabular numerals, dark intel chrome) instead of being buried mid-post; article images land in deliberate spots inside framed figures; posts become comfortable to read (68ch measure, sticky sidebar, no dead-white voids); cards carry data and flare; emails and the price-watch voice get the same polish.
**Size:** L · **Branch:** main (no feature branches — 2026-07-18 convention) · **Depends on:** 01 (verdict badges), 03 (watch mails), 06 (buy-or-wait strip) — all shipped.
**Non-goals:** no rebrand (logo, name, Figtree stay); no new font family; no dark mode; no change to the homepage dark bands' identity (hero, category strip, Drop Price, Editor's Pick, News — they ARE the identity, we extend it); no AdSense slot changes (`<x-ad-unit>` placements stay where they are, still flag-gated); no admin redesign (React admin untouched except the three caption inputs); no route/SEO-meta changes beyond the homepage multi-`<h1>` fix.

Context for the implementer (decided with Bryan, 2026-07-23, after a full code + live audit):

- **Direction: "price-truth instrument."** Light reading surfaces; the dark band system (`#0d0d2b → #0f0a1e → #0a0f1e` gradient, `radial-gradient` dot grid, indigo gradient hairlines) extends from the homepage onto post/listing headers so every page feels like the same site. Tracked-price data is the flare — no other affiliate site can copy it.
- **Root cause of the image-placement complaints:** `ArticleBody::render()` splits on `\n\n` and cuts by block *count*; headings/dividers count as blocks, so images land after headings, against dividers, or at the document end. The fix is structure-aware placement (Phase 10.1), not editorial workarounds.
- **Audit list (what "off" meant):** ~110-char text lines; `bg-gray-50` page vs white cards vs `gray-100` borders ≈ no surface contrast; sidebar dies a third of the way down the post (dead-white right quarter); end-zone is six same-weight gray boxes at uniform `mt-8`; three price widgets stacked between hero and first paragraph; contain-fit images float bare at ~220px on white; five identical Top Picks cards; hidden-until-hover "Read more"; homepage emits one `<h1>` per carousel slide; deals page opens with a half-width text lecture; watch emails bury the savings figure in near-black and lean on em-dashes.

## Phase Log

- [x] Phase 10.1 — ArticleBody structure-aware placement + framed figures + captions (built 2026-07-23; tier1/tier2 slot engine + v2 cache key; 534 tests green; Govee canary verified live; reviewer APPROVE WITH NITS — alt/figcaption fix + tier-2 test applied, orphaned `app-_JpMZc7H.css` bundle cleanup deferred to 10.6; posts:import step was moot, importer sets no image fields; commit pending)
- [ ] Phase 10.2 — Post page structure: intel band, 68ch, sticky sidebar, end-zone rhythm
- [ ] Phase 10.3 — Card system: post cards, Top Picks, sidebar lists
- [ ] Phase 10.4 — Homepage light sections, deals opening, header/footer, `<h1>` fix
- [ ] Phase 10.5 — Email suite + price-watch voice (green money, no em-dashes)
- [ ] Phase 10.6 — QA sweep: responsive, contrast, motion, docs

## Design decisions

- **Tokens.** Dark chrome: reuse the exact band recipe from `category.blade.php`/`home.blade.php` (gradient + dot grid + hairlines) — never invent a second dark. Money is always `tabular-nums`; savings/drops are emerald (`#10b981` in mail, `text-emerald-600` on site); verdict colors stay `<x-verdict-badge>`'s palette. Light surfaces get one step more contrast: cards `border-gray-200` on `bg-gray-50` pages, alternating `bg-white`/`bg-slate-50` section bands, figure mats `bg-gradient-to-br from-slate-50 to-indigo-50/40`. Radius: `rounded-2xl` outer, `rounded-xl` inner. Type scale: post H1 `text-4xl lg:text-5xl` (white, in-band); body `prose-lg` at `max-w-[68ch]`.
- **Image placement rule (10.1).** An inline image may only sit **between two paragraph blocks** of running text — never adjacent to a heading, divider, list, or blockquote, and never at the document end. Second-tier fallback (only when first-tier slots run out): after the final paragraph of a *middle* section. Distribution targets ~30% / 55% / 80% of cumulative text length, minimum two blocks apart. Bodies too short for a slot render images in the last legal position rather than dropping content Bryan uploaded.
- **Figures, not floats (10.1).** Contain-fit images (marketing graphics) sit on a tinted mat card with generous padding; cover-fit images go full column width, rounded, hairline border. New nullable `image_{1,2,3}_caption` columns render as a small centred `<figcaption>`. Captions are optional everywhere (the cloud pipeline doesn't produce them; `posts:import` passes them through when present).
- **Post anatomy (10.2).** Dark intel band holds: type/category eyebrow, H1, byline row, and a one-line intel strip (current price · mini sparkline · verdict chip · checked-at) when `price_intel` exists. Hero image stays BELOW the band on the light surface (white pack-shots keep their background; LCP preload untouched). The three pre-body widgets disperse: intel strip summarises in-band; the full `<x-price-history>` panel + `<x-buy-or-wait-strip>` + watch signup move to the end-zone with the product card. Body starts right after the hero.
- **Reading layout (10.2).** Prose constrained to `max-w-[68ch]`; figures and widgets may span the full article column. Sidebar becomes `lg:sticky lg:top-20 self-start`, trimmed to: "On this page" (H2 anchor list — ArticleBody stamps slug ids on H2s), Related Products (affiliate surface stays visible), one posts list capped at 5.
- **Cards (10.3).** Review cards gain a price + verdict chip row **only when the honesty gates pass** — no placeholder data, ever. Tips/news get their emerald/rose accent treatment instead. Sparklines stay reserved for the intel band and deals cards (grid-scale sparklines are noise). Top Picks: #1 becomes a wide featured card with verdict + one-line why; the rest compact. Hover: 2px lift, type-colored accent bar sweep along the top edge, existing image zoom, "Read more" always visible.
- **Motion budget.** Purposeful micro-motion only: card hover lift/sweep; the intel-band sparkline draws itself once on first viewport entry (~600ms, the signature moment); a one-time glow on "lowest tracked price" chips. Everything behind `prefers-reduced-motion`; nothing animates in the reading column; any JS init must be `alpine:init`-registered and clean up in `destroy()` (wire:navigate rule).
- **Email + watch voice (10.5, Bryan's additions).** Savings/drop dollar amounts in mails are green — including the `watch-drop` headline figure, which currently sits near-black while only the stat cell is green. All watch-surface copy (three mails + the signup widget) loses its em-dashes — rewrite the sentences, don't swap in hyphens — and gets a more memorable, confident voice. `weekly-digest` and `contact-message` get the same pass for consistency.
- **Cache honesty.** `ArticleBody` cache keys gain a version segment (`article-body.v2.`) so the placement rewrite invalidates every cached render on deploy without a manual flush; captions join the hash input.

---

## Phase 10.1 — ArticleBody structure-aware placement + framed figures + captions

**Scope:** the mechanical fix. Migration + model + admin plumbing for captions; the placement rewrite; the figure treatment. No post-page layout changes yet.

Steps:
1. Migration `add_image_captions_to_posts_table`: `image_1_caption`, `image_2_caption`, `image_3_caption` — `nullable()->string()`. Plain columns, sqlite-safe, no driver guard.
2. `Post::$fillable` += the three captions. Admin `PostController` store+update validation: `'image_N_caption' => 'nullable|string|max:255'`.
3. `Form.jsx`: one optional "Caption" text input under each of the three image slots (literal Tailwind classes; match existing field styling). `npm run build` (bundle churn: verify-then-revert; the rebuilt bundle ships with this phase's commit since JSX changed).
4. `ArticleBody` rewrite:
   - Classify `\n\n`-split blocks: heading (`/^#{1,6}\s/`), hr (`/^(-{3,}|\*{3,}|_{3,})\s*$/`), list (`/^([-*+]|\d+\.)\s/m` on first line), blockquote (`/^>/`), else paragraph.
   - First-tier slots: between two consecutive paragraph blocks. Second-tier: after the last paragraph of a middle section (a heading exists later in the doc). Distribute up to three images at ~30/55/80% of cumulative character length, ≥2 blocks apart, order preserved.
   - Output keeps the `[{html, image, fit, caption}]` chunk shape (chunk boundaries at image points; variable chunk count is fine — the Blade component iterates). First chunk still opens with the intro paragraph so the drop-cap selector `.post-body > div:first-child .prose p:first-child` keeps firing.
   - Cache key: `'article-body.v2.'.md5(serialize([$body, $images, $fits, $captions]))`. Still no hard facade dependency (pure-unit-test rule).
5. `article-body.blade.php`: `<figure>` treatment — contain-fit: mat card (`rounded-2xl border border-gray-200/80 bg-gradient-to-br from-slate-50 to-indigo-50/40 p-6 sm:p-8`, image centred `max-h-96 w-auto max-w-full rounded-lg shadow-sm` via `<x-adaptive-image>`); cover-fit: full-width `rounded-2xl border border-gray-200/80` `max-h-[28rem]`. `<figcaption>` centred `text-sm text-gray-500 mt-3` when a caption exists. No Blade directives inside component tags (bound attributes only).
6. `PublicController::show()` passes the caption array; `posts:import` maps optional `image_N_caption` keys when the JSON carries them (no build-script change).

Tests (`tests/Unit/ArticleBodyTest.php` — stays a pure PHPUnit unit test):
- An image never renders adjacent to a heading block (body with `## H2` right at a thirds boundary — the old failure).
- An image never lands after the final block when a first-tier slot exists.
- Three images distribute in order, ≥2 blocks apart, on a long mixed body.
- Caption passthrough; missing captions yield `null`.
- Short bodies (1–2 paragraphs) still render every image (fallback slot).
- Existing hr/blockquote/markdown/empty-body cases updated to the new shape.
- Feature: `PublicPagesTest` post-page case asserts a `<figure>` + caption render (one seeded caption).

Commit: `feat(design): structure-aware article image placement, framed figures, captions`

Review checklist: cache key versioned; no facade hard-dep in ArticleBody; drop-cap DOM shape preserved; captions escaped (`{{ }}`); adaptive-image still used (variants!); admin validation covers all three; bundle rebuilt.

## Phase 10.2 — Post page structure: intel band, 68ch, sticky sidebar, end-zone rhythm

**Scope:** `show.blade.php` restructure + a compact intel-strip component + sidebar "On this page". No card changes.

Steps:
1. Dark intel band (reuse the category-hero recipe verbatim): eyebrow (`{type} · {category}`), H1 `text-4xl lg:text-5xl font-extrabold text-white`, byline/meta row, share bar, affiliate disclosure (restyled for dark or placed immediately under the band — pick whichever keeps contrast AA).
2. `<x-intel-strip :stats>`: one-line summary — `$price` (`tabular-nums`) · 90-day mini sparkline (reuse `PriceIntel::sparklinePoints`) · `<x-verdict-badge>` · "checked {diffForHumans}". Renders nothing without stats (tips/news/priceless: band simply has no strip). Sparkline draw-in animation registered on `alpine:init` with `destroy()` cleanup, `prefers-reduced-motion` → static.
3. Hero image below the band; body immediately after. Pre-body widget stack removed; `<x-price-history>` + `<x-buy-or-wait-strip>` + `@livewire('price-watch-signup')` regroup in the end-zone around `<x-product-card>`.
4. Prose wrapper `max-w-[68ch] prose-lg`; figures/widgets keep full column width.
5. ArticleBody `style()` pass stamps slug ids on `<h2>`s; sidebar gains "On this page" anchor list; sidebar `lg:sticky lg:top-20 self-start`, trimmed to On-this-page + Related Products + one capped(5) posts list.
6. End-zone rhythm: verdict box, vote, tags, source, author restyled on the new surface scale (alternating white/slate-50, `border-gray-200`) with varied weights instead of six identical boxes.

Tests: `PublicPagesTest` — post page still one `<h1>`, H1 text unchanged, intel strip appears for a product with open gates and NOT for a tip; anchor ids present on H2s. `PublicLayoutTest` wire:navigate assertions still green.

Commit: `feat(design): post intel band, 68ch measure, sticky sidebar, end-zone rhythm`

Review checklist: LCP preload path untouched (hero still `loading=eager fetchpriority=high`, band adds no image); serverMeta/JsonLd untouched; single-CTA rule intact (strip has no link/CTA); Livewire components keep working after relocation; sticky sidebar has `self-start` (grid stretch trap); drop cap still fires.

## Phase 10.3 — Card system: post cards, Top Picks, sidebar lists

**Scope:** `post-card.blade.php` (+ the inline copy in `category.blade.php` — extract to the component while there), home Top Picks block, `sidebar-section`/`author-post-card` alignment.

Steps:
1. `x-post-card`: type accent system (indigo review / emerald tip / rose news) — static top hairline that brightens + sweeps on hover; 2px lift; image zoom kept; "Read more" always visible; price + `<x-verdict-badge size=sm>` row on review cards when the post's product has open gates (controller passes `card_price`/`card_verdict` in the existing post arrays — computed from the already-cached `PriceIntel::stats`, null-safe).
2. `category.blade.php` swaps its duplicated card markup for `<x-post-card>` (drift kill).
3. Top Picks: first product renders as a 2-col featured card (bigger image, verdict chip, one-line editorial why from the post excerpt); remainder compact; identical-button repetition broken (featured gets the filled CTA, rest get quiet text links).
4. `sidebar-section` + `author-post-card`: surface/border alignment to the new scale, no structural change.

Tests: `PublicPagesTest` home + category — verdict chip renders only when gates open (seed one product with span-open history, one without); card link/wire:navigate intact. Suite green.

Commit: `feat(design): data-bearing card system with type accents`

Review checklist: honesty gates respected (no chip without stats); no N+1 (stats come from the db-cache per product, posts already eager-load products); purge-safe literal classes for all three accent variants; `min-w-0`/truncate discipline on flex rows.

## Phase 10.4 — Homepage light sections, deals opening, header/footer, `<h1>` fix

**Scope:** homepage light-band shells, deals page lede, public header/footer presence, carousel heading semantics.

Steps:
1. Hero carousel: slide 1 keeps `<h1>`, other slides become `<h2>` (or `<p role=heading>`-free plain heading markup) — one `<h1>` per page.
2. Light sections (Top Picks, Recent Drops, Tools, Join the Drop): surface alternation (white/slate-50), section-header eyebrow style unified with the dark bands' (`w-6 h-px` + tracking label — already close; make identical), spacing rhythm normalised (consistent `py-16`).
3. Deals page: lede compressed to two sentences + a stat chip row (products tracked · drops live now · biggest current drop %, from existing feed data); methodology paragraph collapses behind a "How we call it" details/disclosure link to `how-we-review`; cards move above the fold.
4. Header: Deals nav item gets a live count pill (deals feed count, cached — subtle, `text-xs` chip); active-section underline; footer link columns aligned to the eyebrow style. No new nav items.
5. `robots`/SEO untouched; `serverMeta` untouched.

Tests: `PublicPagesTest` homepage exactly one `<h1>`; `DealsPageTest` still green (lede text assertions adjusted if they pinned copy); `SitemapTest` untouched.

Commit: `feat(design): homepage rhythm, deals lede, header presence, single h1`

Review checklist: hero LCP preload still matches slide 1; no `wire:navigate` added to `/out` or tools links; deals stat chips honest (no stats → chip hidden); nav count query cached on the database store with a sane TTL + flush hook.

## Phase 10.5 — Email suite + price-watch voice

**Scope:** `mail/watch-verify`, `mail/watch-drop`, `mail/watch-closing`, `mail/weekly-digest`, `mail/contact-message`, `livewire/price-watch-signup.blade.php`. Bryan's direct asks live here.

Steps:
1. **Green money:** every savings/drop figure renders `#10b981` — including the `watch-drop` `<h1>` amount ("It dropped $X" is currently near-black; the reader's eye should land on green money instantly). Paid stays muted, current price near-black, savings green — the existing stat-table hierarchy, applied everywhere consistently.
2. **Em-dash purge:** rewrite every em-dash sentence across the five templates + the signup widget into short declarative sentences (not hyphen swaps). Grep-verify `—` and `&mdash;` are gone from `resources/views/mail/` and the signup view.
3. **Memorable watch voice:** signup widget copy rewritten around the concrete promise (return-window watching, two-email max, address deleted after) with a hook line; mails keep the "Back in your pocket" register and extend it (subject-adjacent preview text included). No claims beyond what the system does; Amazon §6(y) rule — this is post-purchase return-window watching, never pre-purchase drop alerts; don't drift the copy toward "price alerts."
4. Shared visual pass: consistent logo block, eyebrow monospace labels, button radius/weight, footer link styling across all five mails (inline styles only, table layout preserved, `color-scheme: light` kept).

Tests: existing watch-mail tests (Plan 03) re-run; where they assert copy strings, update to the new copy. Add assertions: `watch-drop` rendered HTML contains the green hex on the headline savings span; rendered mails contain no `—`.

Commit: `feat(design): email polish, green savings, em-dash-free watch voice`

Review checklist: no em-dash anywhere in mail views or signup widget; unsubscribe/verify links untouched; preview-text div intact; §6(y) language discipline; Dusk `watch-email`/`watch-submit` selectors preserved.

## Phase 10.6 — QA sweep

**Scope:** verification + docs, minimal code (fixes found by the sweep only).

Steps:
1. Browser pass at 1512 / 768 / 390 widths: home, review post (with + without stats), tip, news, category, deals, tools index, search — screenshot set + the CLAUDE.md horizontal-scroll console snippet on each.
2. Contrast spot checks (worst offenders: gray-400-on-white metadata, dark-band gray-500 text) — bump anything under WCAG AA for body-size text.
3. `prefers-reduced-motion` emulation: sparkline static, hover sweeps off, carousel/Drop-Price already compliant.
4. `php artisan view:clear` + `npm run build` + full suite; verify no purged-class regressions.
5. Docs: CLAUDE.md (ArticleBody section note: structure-aware placement + captions; post anatomy note), CONTENT-GUIDELINES.md (optional captions), plans README row check, memory update.

Tests: full suite green is the gate; no new tests unless the sweep finds a bug (then test-with-fix).

Commit: `chore(design): refresh QA sweep, docs, contrast fixes`

## Deployment

Shared template (plans README) — deltas: Phase 10.1 ships a migration (`migrate --force` applies it; captions are nullable so zero backfill). After each deployed phase: purge Hostinger CDN and hard-refresh the affected surfaces. After 10.1: spot-check three long reviews for image placement (the Govee review is the canary). After 10.2: verify LCP on a review page hasn't regressed (PageSpeed spot check) and the Livewire endpoints (vote, watch signup) still fire post-`route:cache`. After 10.5: send one real watch-verify + watch-drop to a test address (Mailtrap or Bryan's inbox) and eyeball rendering in Gmail.

## Rollback

10.1 migration down drops the three caption columns (content loss limited to captions). Every other phase is template/CSS only — revert the commit, `npm run build`, redeploy, CDN purge. The `article-body.v2.` cache version means a revert to v1 code re-renders cleanly too (old keys still valid).
