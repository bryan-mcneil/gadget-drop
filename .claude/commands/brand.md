# /brand — Brand & Design Consistency Auditor

You are a brand and design systems expert reviewing GadgetDrop's visual identity across the entire site. Your job is to find inconsistencies and define a clear, repeatable design system the whole site can follow.

## What to do

Read the following files in full before producing any output:

**Layouts**
- `resources/js/Layouts/PublicLayout.jsx`
- `resources/js/Layouts/AuthenticatedLayout.jsx`

**Public pages**
- `resources/js/Pages/Public/Home.jsx`
- `resources/js/Pages/Public/Post.jsx`
- `resources/js/Pages/Public/Category.jsx`

**Admin pages**
- `resources/js/Pages/Admin/Dashboard.jsx`
- `resources/js/Pages/Admin/Posts/Form.jsx`
- `resources/js/Pages/Admin/Posts/Index.jsx`
- `resources/js/Pages/Admin/Products/Form.jsx`

**Styles**
- `tailwind.config.js`
- `resources/css/app.css`

## Audit checklist

Score each item ✅ / ⚠️ / ❌:

### Color
- [ ] A single primary color (indigo) is used consistently for CTAs, links, and active states
- [ ] Affiliate CTA buttons ("View on Amazon") are consistently orange (`orange-500`) across all pages
- [ ] Background colors follow a clear hierarchy: page bg → card bg → input bg
- [ ] No one-off colors that appear only once and could be replaced by a standard Tailwind shade

### Typography
- [ ] One font family in use (Figtree) — no accidental fallbacks rendering differently
- [ ] Heading sizes follow a consistent scale (text-3xl → text-xl → text-lg → text-base)
- [ ] Label styles (uppercase tracking-wide text-sm) used consistently for section headers
- [ ] Body text is consistently `text-gray-700` or `text-gray-600` — not mixed

### Spacing & Layout
- [ ] Card padding is consistent (`p-4` or `p-5`) — not mixed across components
- [ ] Section spacing (`space-y-6`, `gap-6`) follows a rhythm, not random values
- [ ] Max-width containers (`max-w-6xl`, `max-w-5xl`) are consistent across public pages
- [ ] Border radius is consistent (`rounded-xl` for cards, `rounded-lg` for inputs/buttons)

### Component Patterns
- [ ] Buttons of the same role look identical across pages (same padding, radius, weight)
- [ ] Tag/badge styles (categories, status chips) use the same pattern everywhere
- [ ] Empty states exist and are styled consistently
- [ ] Form fields use the same Tailwind border/focus/shadow pattern throughout

### Brand Voice Alignment
- [ ] Page titles and section headings feel consistent in tone (not some formal, some casual)
- [ ] Disclosure and legal copy is styled the same way wherever it appears
- [ ] Footer is present on all public pages with the Amazon Associates disclosure

## Output

Write all output to `brand-review.md` in the project root using the Write tool. Tell the user the file is ready with a one-line summary.

Structure the file as:

### 1. Inconsistencies Found
Table of every ✅/⚠️/❌ with the specific files and Tailwind classes that differ.

### 2. Recommended Design Tokens
A definitive mini design system for GadgetDrop — the single source of truth for colors, type scale, spacing, and border radius. Expressed as Tailwind class names so they can be applied directly.

```
Primary action:     bg-indigo-600 text-white hover:bg-indigo-700
Affiliate CTA:      bg-orange-500 text-white hover:bg-orange-600
Card container:     bg-white border border-gray-200 rounded-xl p-5
Section label:      text-xs font-semibold text-gray-500 uppercase tracking-wide
Body text:          text-sm text-gray-600
Heading (H1):       text-3xl font-extrabold text-gray-900
Heading (H2):       text-xl font-semibold text-gray-800
```

### 3. Priority Fix List
Top 5 inconsistencies ranked by visual impact — most jarring to a first-time visitor first.

### 4. Suggested Tailwind Config Extensions
Any `theme.extend` additions (custom colors, font sizes) worth adding to `tailwind.config.js` to lock in the brand tokens.
