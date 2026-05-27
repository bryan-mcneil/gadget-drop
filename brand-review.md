# Brand & Design Consistency Audit — GadgetDrop
**Reviewed:** 2026-05-20

---

## 1. Inconsistencies Found

### Color

| Check | Status | File(s) | Detail |
|---|---|---|---|
| Indigo is the single primary color for CTAs / links / active states | ⚠️ | `Home.jsx:19` | Hero CTA uses `bg-indigo-500` — every other primary button site-wide uses `bg-indigo-600` |
| Affiliate CTA buttons are consistently `orange-500` | ✅ | `Post.jsx:124` | `bg-orange-500 hover:bg-orange-600` — only one occurrence, correct |
| Background hierarchy is clear | ⚠️ | `Dashboard.jsx:21,49` | Stat cards and the recent-posts table use `shadow` without a border (`bg-white rounded-xl shadow`). All other cards across public and admin use `bg-white border border-gray-200 rounded-xl`. Two competing "card" patterns. |
| No one-off colors | ✅ | — | Yellow (disclosures), green (success flash/badge), red (delete/errors) are all functional and reused |

### Typography

| Check | Status | File(s) | Detail |
|---|---|---|---|
| Single font family (Figtree) | ✅ | `tailwind.config.js` | Only Figtree configured; `app.css` is bare |
| Heading scale is consistent | ⚠️ | `Home.jsx:14`, `Post.jsx:28`, `Category.jsx:12` | H1 is `text-4xl` on the Home hero but `text-3xl` on Post and Category pages. All three are the page-level H1. |
| Section label style consistent | ✅ | Multiple | `text-sm font-semibold text-gray-700 uppercase tracking-wide` used correctly across all public sidebars and admin panel cards |
| Body text is consistent | ⚠️ | `Home.jsx:67`, `Post.jsx:119`, `Category.jsx:29` | Excerpt / description text uses `text-gray-500` throughout public pages. Form labels use `text-gray-700`. Navigation uses `text-gray-600`. These are intentional hierarchies but never defined — `text-gray-500` is softer than the target `text-gray-600` for readable body copy |
| H2 weight is consistent | ⚠️ | `Home.jsx:29`, `Dashboard.jsx:13` | "Recent Drops" H2 uses `font-bold`; Admin header H2s use `font-semibold` |

### Spacing & Layout

| Check | Status | File(s) | Detail |
|---|---|---|---|
| Card padding is consistent | ⚠️ | `Dashboard.jsx:21`, `Products/Form.jsx:32` | Most cards use `p-5`. Dashboard stat cards and the Products/Form main card use `p-6`. |
| Section spacing follows a rhythm | ⚠️ | Multiple | `space-y-4` (PostsIndex), `space-y-5` (form columns), `space-y-6` (public sidebar), `space-y-8` (Dashboard) — four different values across similar vertical stacks |
| Max-width containers consistent | ✅ | Public pages | `max-w-6xl` on all public content grids; `max-w-7xl` on all admin lists/dashboard; smaller for focused forms. Intentional and clean. |
| Border radius consistent | ⚠️ | `Home.jsx:19` | Hero CTA button uses `rounded-xl`. All other buttons (admin and public) use `rounded-lg`. Cards correctly use `rounded-xl` throughout. |

### Component Patterns

| Check | Status | File(s) | Detail |
|---|---|---|---|
| Same-role buttons look identical | ❌ | `Home.jsx:19` vs `Dashboard.jsx:31`, `Posts/Form.jsx:153` | Hero CTA: `bg-indigo-500 font-semibold px-6 py-3 rounded-xl transition`. All other primary buttons: `bg-indigo-600 font-medium px-4 py-2 rounded-lg`. Wrong shade, wrong weight, wrong radius, wrong size. |
| Tag/badge styles consistent | ⚠️ | `Post.jsx:25`, `Post.jsx:56` | Category tags: `px-2 py-1 rounded-full`. Post/topic tags: `px-3 py-1 rounded-full`. One extra pixel of horizontal padding on topic tags for no reason. |
| Empty states exist and are consistent | ⚠️ | `Home.jsx`, `Category.jsx` | `CheckboxGroup` in admin has a "None yet." empty state. Post grid in Home and Category has no empty state — a blank grid if there are no posts. |
| Form fields use consistent border/focus/shadow | ✅ | All forms | `border-gray-300 rounded-lg shadow-sm` used on every input, textarea, and select |

### Brand Voice Alignment

| Check | Status | File(s) | Detail |
|---|---|---|---|
| Page titles/headings consistent in tone | ✅ | — | Public pages use informal ("Today's Drop", "Recent Drops"); admin pages use functional ("Posts", "Dashboard"). Different audiences — intentional split. |
| Disclosure copy is the same wherever it appears | ❌ | `Home.jsx:76`, `Post.jsx:62`, `PublicLayout.jsx:21` | Three different phrasings: (1) "GadgetDrop is a participant in the Amazon Services LLC Associates Program. We earn a small commission…" (2) "GadgetDrop participates in the Amazon Associates program. We may earn a commission…" (3) "As an Amazon Associate we earn from qualifying purchases" |
| Disclosure styling consistent | ⚠️ | `Home.jsx:75`, `Post.jsx:61` | Both use `bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-xs text-yellow-800` — but Home's version adds `leading-relaxed`, Post's does not. Not a shared component. |
| Footer on all public pages with disclosure | ✅ | `PublicLayout.jsx:20` | Footer is in the layout and renders on every public page |
| Disclosure box in sidebar on all public pages | ⚠️ | `Category.jsx` | Home sidebar renders `<AffiliateDisclosure />`. Category sidebar has no disclosure box. Post sidebar has no disclosure box either (the disclosure appears inline below the body instead). |

---

## 2. Recommended Design Tokens

Single source of truth for GadgetDrop. Apply these Tailwind classes directly.

```
/* Buttons */
Primary (default):      bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition
Primary (hero/large):   bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition
Affiliate CTA:          bg-orange-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-orange-600 transition
Secondary:              bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50
Danger (text link):     text-red-500 hover:underline text-sm

/* Cards */
Standard card:          bg-white border border-gray-200 rounded-xl p-5
Tinted panel (SEO/etc): bg-gray-50 border border-gray-200 rounded-xl p-5
Disclosure box:         bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-xs text-yellow-800 leading-relaxed

/* Typography */
Page H1:                text-3xl font-extrabold text-gray-900 leading-tight
Hero H1 (dark bg):      text-4xl font-extrabold leading-tight   (dark-bg hero sections only)
H2 section header:      text-xl font-semibold text-gray-800
Section label:          text-sm font-semibold text-gray-700 uppercase tracking-wide
Body text:              text-sm text-gray-600
Meta/timestamp:         text-xs text-gray-400

/* Tags */
Category tag (indigo):  text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-medium
Topic tag (gray):       text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full font-medium
Status badge published: text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium
Status badge draft:     text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium
Status badge scheduled: text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-medium

/* Layout */
Public content width:   max-w-6xl mx-auto px-4
Admin content width:    max-w-7xl mx-auto px-4
Hero inner width:       max-w-4xl mx-auto
Page bg (public):       bg-gray-50
Page bg (admin):        bg-gray-100

/* Disclosure text (canonical) */
"GadgetDrop participates in the Amazon Associates program.
 We earn a small commission on qualifying purchases at no extra cost to you."
```

---

## 3. Priority Fix List

Ranked by visual impact to a first-time visitor.

### 1. Hero CTA button — wrong shade, radius, and weight `Home.jsx:19`
The most prominent button on the entire site uses `bg-indigo-500 rounded-xl font-semibold` while every other primary button uses `bg-indigo-600 rounded-lg font-medium`. A visitor who reads the homepage then clicks into an article sees the primary color shift. Fix:

```jsx
// Before
className="mt-6 inline-block bg-indigo-500 hover:bg-indigo-600 text-white font-semibold px-6 py-3 rounded-xl transition"

// After
className="mt-6 inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition"
```

### 2. Disclosure wording — three different phrasings across three files
Legal copy should be identical everywhere. Extract to a single `<AffiliateDisclosure />` component and use it in `Home.jsx` sidebar, `Post.jsx` body, and update `PublicLayout.jsx` footer to match:

```jsx
// Canonical disclosure text:
"GadgetDrop participates in the Amazon Associates program. We earn a small
commission on qualifying purchases at no extra cost to you."

// Category.jsx sidebar is also missing the disclosure box entirely — add it.
```

### 3. Dashboard card style — `shadow` vs `border` split `Dashboard.jsx:21,49`
Stat cards and the recent-posts table use `shadow` with no border, breaking the `bg-white border border-gray-200 rounded-xl` pattern used on every other card in the app. Fix by adding `border border-gray-200` to the stat card and removing the lone `shadow` (or using `shadow-sm` as an enhancement on top of the border, which several public cards already do on hover).

```jsx
// Stat card — before
className="bg-white rounded-xl shadow p-6 hover:shadow-md transition"

// After
className="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-sm transition"
```

### 4. H1 scale — `text-4xl` hero vs `text-3xl` article pages
`Home.jsx` hero uses `text-4xl` while `Post.jsx` and `Category.jsx` use `text-3xl` for the page H1. Reserve `text-4xl` for the single full-bleed dark hero (Home only). Standardise all article/category H1s at `text-3xl`. Current Post and Category are already correct — only the Home hero is the exception. This is actually fine if documented as intentional; the issue is it's not documented, so it looks like drift.

**Decision:** Keep `text-4xl` only for the dark-background hero section on Home. Codify this in the token table above.

### 5. Topic tag padding — `px-3` vs `px-2` `Post.jsx:56`
Category tags use `px-2 py-1`; post tags use `px-3 py-1`. Both sit on the same page. Fix the topic tags to match:

```jsx
// Before
className="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full"

// After
className="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full font-medium"
```

---

## 4. Suggested Tailwind Config Extensions

The current config has a single font extension. Add brand tokens to lock them in:

```js
// tailwind.config.js
theme: {
    extend: {
        fontFamily: {
            sans: ['Figtree', ...defaultTheme.fontFamily.sans],
        },
        colors: {
            brand: {
                DEFAULT: '#4f46e5', // indigo-600 — primary action
                hover:   '#4338ca', // indigo-700
                light:   '#e0e7ff', // indigo-100 — tag backgrounds
            },
            affiliate: {
                DEFAULT: '#f97316', // orange-500 — Amazon CTA
                hover:   '#ea580c', // orange-600
            },
        },
        maxWidth: {
            content: '72rem',  // 1152px — maps to max-w-6xl (public)
            admin:   '80rem',  // 1280px — maps to max-w-7xl (admin)
        },
    },
},
```

Using `bg-brand` and `bg-affiliate` across the codebase eliminates the indigo-500/indigo-600 drift problem at the source — any future button will default to the correct shade without manual checking.
