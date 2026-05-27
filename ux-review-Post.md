# UX & Conversion Review — Post Page
**File:** `resources/js/Pages/Public/Post.jsx`
**Reviewed:** 2026-05-20

---

## 1. Conversion Score: 5/10

The page has the right ingredients — orange CTA, price near the button, author trust signals — but the execution leaves significant revenue on the table. The biggest problem is structural: on any post with a featured image, the affiliate button sits more than 600px below the fold. A reader who bounces after the hero image never sees the product. Compounding this, product images are thumbnail-sized (80×80px) and there's no second CTA after the body, so long posts have a single conversion opportunity that most readers scroll past. Fix those three issues and this page can convert well.

---

## 2. Checklist

### Conversion & Affiliate Performance

| Check | Status | Location | Detail |
|---|---|---|---|
| CTA above the fold | ❌ | `Post.jsx:34–45` | Header (56px) + padding (48px) + categories + H1 + byline + featured image (`max-h-80` = 320px) = ~550–600px before the first "View on Amazon" button. Below the fold on any standard viewport. |
| CTA button prominence | ⚠️ | `Post.jsx:124` | Orange button is correct (`bg-orange-500`), but it's `text-sm px-4 py-2` — modest size. The price at `text-lg font-bold` is visually larger than the button that should dominate. |
| Price visibility | ✅ | `Post.jsx:121` | `text-lg font-bold text-gray-900` immediately left of the CTA. Good. |
| Product image size | ❌ | `Post.jsx:115` | `w-20 h-20` (80×80px) with `object-contain`. Far too small to build desire for a $29–$600 product. Feels like a thumbnail, not a product showcase. |
| Trust signals | ✅ | `Post.jsx:29–31, 66–86` | Author byline present. Author card at bottom with avatar and bio. Disclosure box present. |
| CTA repetition for long posts | ❌ | `Post.jsx:39–45` | Products render once, before the body. No affiliate button appears after the article body. Long posts (700+ words) have a single, pre-scroll conversion point. |

### Visual Hierarchy & Readability

| Check | Status | Location | Detail |
|---|---|---|---|
| F-pattern scan | ✅ | `Post.jsx:21–32` | Category chips → H1 → byline → image → products → body. Good top-down flow. |
| Heading structure | ⚠️ | `Post.jsx:118` | Page has H1 (post title). Product cards use H3 (`font-semibold text-gray-900`) but there's no H2 wrapping the products section. H1 → H3 skips a level. |
| Line length | ❌ | `Post.jsx:48` | `<div className="prose prose-gray max-w-none">` — `max-w-none` overrides Tailwind Typography's built-in `max-w-prose` (~65 characters). On a 3/4-column layout at 1280px, body text spans ~864px, which is ~120+ characters per line. Well beyond the 60–75 char readable threshold. |
| Contrast | ⚠️ | `Post.jsx:29` | `text-gray-400` for byline text ("By Maya Reeves · Jan 2026") is approximately 4.3:1 — marginally below WCAG AA (4.5:1) for normal-size text. |
| Section breaks | ✅ | `Post.jsx:48` | ReactMarkdown with prose styles handles body sectioning. Depends on post content having headings, which is the author's responsibility. |

### Layout & Responsiveness

| Check | Status | Location | Detail |
|---|---|---|---|
| Functional at 375px | ✅ | `Post.jsx:18` | `grid-cols-1 lg:grid-cols-4` — single column on mobile. ProductCard `flex gap-4` with 80px image leaves ~260px for text. Fine. |
| Images have max-height | ✅ | `Post.jsx:35` | Featured image: `max-h-80`. Product images: fixed 80×80. |
| Sidebar stacks correctly on mobile | ✅ | `Post.jsx:89–105` | Sidebar is last in DOM order; stacks below article on mobile. |
| No horizontal overflow | ✅ | — | No obvious overflow sources. |

### Accessibility & Compliance

| Check | Status | Location | Detail |
|---|---|---|---|
| Descriptive alt attributes | ⚠️ | `Post.jsx:36, 115` | Featured image: `alt={post.title}` (same as H1 — redundant duplication, not descriptive of the image). Product image: `alt={product.name}` (same as the H3 directly above it — screen reader reads it twice). |
| Affiliate links have `rel="nofollow sponsored"` | ✅ | `Post.jsx:123` | Correctly set. |
| Focus states visible | ⚠️ | All buttons/links | No explicit `focus:ring` classes on the CTA button or navigation links. Relies on browser defaults, which are often invisible in Chrome. |
| Disclosure present | ✅ | `Post.jsx:61–63` | Yellow disclosure box in article body. Footer also has disclosure via `PublicLayout`. |

### Code Quality

| Check | Status | Location | Detail |
|---|---|---|---|
| No hardcoded pixel values | ✅ | — | All spacing from Tailwind scale. |
| No unsafe `dangerouslySetInnerHTML` | ✅ | `Post.jsx:49` | ReactMarkdown used for body — correct. |
| All list items have `key` props | ✅ | `Post.jsx:23, 42, 55, 95` | All `.map()` calls include `key={item.id}`. |
| `loading="lazy"` on below-fold images | ❌ | `Post.jsx:69, 115` | Author avatar (`w-14 h-14`, far below fold) and product images (below featured image) have no `loading="lazy"`. Featured image can stay eager; the others should lazy-load. |

---

## 3. Quick Wins

### Win 1 — Move products above the featured image (highest revenue impact)

The featured image is decorative. The product card with the affiliate button is the reason the post exists. Swap their render order so the CTA is visible without scrolling.

```jsx
// Post.jsx — current order (lines 34–49):
{post.featured_image && (
    <img ... className="w-full rounded-xl mb-8 object-cover max-h-80" />
)}
{post.products.length > 0 && (
    <div className="mb-8 space-y-4">
        {post.products.map((product) => (
            <ProductCard key={product.id} product={product} postId={post.id} />
        ))}
    </div>
)}

// After — swap them:
{post.products.length > 0 && (
    <div className="mb-8 space-y-4">
        {post.products.map((product) => (
            <ProductCard key={product.id} product={product} postId={post.id} />
        ))}
    </div>
)}
{post.featured_image && (
    <img ... className="w-full rounded-xl mb-8 object-cover max-h-80" />
)}
```

### Win 2 — Increase product image size from 80px to 128px

80×80 is icon territory. Doubling to 128px builds desire without breaking the card layout.

```jsx
// ProductCard — Post.jsx line 115
// Before:
<img src={product.image_url} alt={product.name} className="w-20 h-20 object-contain rounded-lg flex-shrink-0" />

// After:
<img src={product.image_url} alt={product.name} loading="lazy"
    className="w-32 h-32 object-contain rounded-lg flex-shrink-0" />
```

Also add `loading="lazy"` to the author avatar:

```jsx
// Post.jsx line 69
// Before:
<img src={post.user.avatar_url} alt={post.user.name}
    className="w-14 h-14 rounded-full object-cover flex-shrink-0" />

// After:
<img src={post.user.avatar_url} alt={post.user.name} loading="lazy"
    className="w-14 h-14 rounded-full object-cover flex-shrink-0" />
```

### Win 3 — Fix prose line length by removing `max-w-none`

```jsx
// Post.jsx line 48
// Before:
<div className="prose prose-gray max-w-none">

// After:
<div className="prose prose-gray">
```

`prose` without `max-w-none` applies `max-w-prose` (~65ch) automatically, keeping body text in the readable sweet spot. The column is already constrained by the 3/4 grid layout — this just adds the inner prose constraint.

---

## 4. Bigger Improvements

### Improvement 1 — Repeat the affiliate CTA after the body

Readers who finish the article are warm and conversion-ready — and there's nothing to click. Add a condensed product recap after the body, before the tags section.

```jsx
// Add after the </div> closing the prose block (after line 50), before the tags section:

{post.products.length > 0 && (
    <div className="mt-10 pt-8 border-t border-gray-100">
        <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
            Check Current Prices
        </h2>
        <div className="space-y-3">
            {post.products.map((product) => (
                <div key={product.id} className="flex items-center justify-between gap-4 bg-gray-50 rounded-xl px-5 py-3">
                    <div className="min-w-0">
                        <p className="font-semibold text-gray-900 text-sm truncate">{product.name}</p>
                        {product.price && <p className="text-sm text-gray-500">${product.price}</p>}
                    </div>
                    <a href={route('affiliate.redirect', { product: product.id, post: post.id })}
                        target="_blank" rel="nofollow sponsored"
                        className="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                        View on Amazon →
                    </a>
                </div>
            ))}
        </div>
    </div>
)}
```

### Improvement 2 — Make the ProductCard CTA button larger

The current `px-4 py-2 text-sm` button is undersized for the most revenue-critical element on the page. The price `text-lg font-bold` is visually larger than the action button — hierarchy is inverted.

```jsx
// ProductCard — current (Post.jsx line 120–127):
<div className="mt-3 flex items-center gap-3">
    {product.price && <span className="text-lg font-bold text-gray-900">${product.price}</span>}
    <a ... className="bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
        View on Amazon →
    </a>
</div>

// After — stack vertically, make button full-width of its container section:
<div className="mt-4">
    {product.price && (
        <p className="text-2xl font-bold text-gray-900 mb-2">${product.price}</p>
    )}
    <a ... className="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold px-6 py-3 rounded-lg transition">
        View on Amazon →
    </a>
</div>
```

Moving the price above the button and enlarging the button to `px-6 py-3` gives it visual dominance — which is what converts.

---

## 5. Accessibility & Compliance Issues

### ❌ `loading="lazy"` missing on below-fold images
**Post.jsx lines 69, 115**
Not a legal risk, but affects Core Web Vitals (LCP) and page load. Add `loading="lazy"` to product images and author avatar. Featured image (`Post.jsx:35`) is near the top and should stay without lazy-loading.

### ⚠️ `alt` attributes are redundant, not descriptive
**Post.jsx lines 36, 115**
- `alt={post.title}` on the featured image repeats the H1 — a screen reader reads the title twice in succession.
- `alt={product.name}` on the product image repeats the H3 — same duplication problem.

Recommended fix:
```jsx
// Featured image
alt={`Featured image for ${post.title}`}

// Product image
alt={`Product photo: ${product.name}`}
```

Not a legal risk, but it's a meaningful accessibility improvement and may affect image search indexing.

### ⚠️ No explicit `focus:ring` on affiliate CTA
**Post.jsx line 122–124**
The "View on Amazon" anchor has no `focus:ring` class. Keyboard-only users and screen reader users navigate with Tab — without a visible focus ring, they can't see which element is active.

```jsx
// Add to the CTA className:
className="... focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2"
```

### ⚠️ Disclosure position — post-scroll
**Post.jsx line 61**
The disclosure box renders *after* the product cards and the entire body. A user could click "View on Amazon" and navigate away before seeing any disclosure. The FTC and Amazon Associates ToS require the disclosure to be "clear and conspicuous" — ideally visible before the first affiliate link. Consider moving it to just below the byline (before the product cards), or at minimum above the first ProductCard.
