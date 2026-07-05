# /drop-refresh — Sunday: Refresh a Decaying / Striking-Distance Post

## Context
GadgetDrop's Search Intel layer mines `search_opportunities` from real Search Console + Bing data. This command runs **Sundays** (the review-only day) and turns the top actionable opportunity into a **genuine update of an existing published post** — new price context, expanded answers to the queries people actually search, better internal linking, and truthful title/meta fixes. Run it **locally, with database access** (it reads opportunities + `PriceIntel` and edits a live post). Not for the cloud agent (no DB there).

> **This is a real update, never a date-bump.** Google's spam policy treats re-dating unchanged content as a violation. Every refresh must add real value and earn its new `updated_at`. If there's nothing substantive to add, stop and say so.

## Step 1 — Pick the target
Find the highest-value open/planned opportunity to act on:
```bash
php artisan tinker --execute="echo App\Models\SearchOpportunity::whereIn('status',['open','planned'])->whereIn('kind',['decay','striking_distance','ctr_fix'])->orderByDesc('score')->with('post')->get()->map(fn(\$o)=>['id'=>\$o->id,'kind'=>\$o->kind,'score'=>(string)\$o->score,'post'=>\$o->post?->slug,'query'=>\$o->query,'evidence'=>\$o->evidence])->toJson(JSON_PRETTY_PRINT);"
```
- Prefer **decay** (a post losing clicks) and **striking_distance** (ranks 4–15, one push from page 1). Bundle any **ctr_fix** opportunity for the *same post* into the same refresh.
- No opportunities? Run `php artisan search:mine` first (it needs synced data). Still none → tell the user there's nothing to refresh today and stop.
- Note the `post` slug, the `query`, and the clustered `evidence.phrasings` (the related searches the refresh should answer).

## Step 2 — Read the current post + price context
```bash
php artisan tinker --execute="\$p=App\Models\Post::where('slug','{SLUG}')->first(); echo json_encode(['title'=>\$p->title,'body'=>\$p->body,'product'=>\$p->products->first()?->id]);"
```
If the post has a product, pull honest price context (never invent numbers):
```bash
php artisan tinker --execute="print_r(App\Support\PriceIntel::stats({PRODUCT_ID}));"
```
Use only what `PriceIntel` returns (current, tracked low/avg/high, verdict). The live widget renders the real history; the body may reference it qualitatively.

## Step 3 — Refresh the content (honestly)
Rewrite/expand the post to genuinely serve the target query cluster, keeping the voice and rules from `/drop-write` (Bryan McNeil; no first-hand-testing claims; no em dashes; no banned phrases; **no in-body Amazon links** — the product card stays the single CTA):
- **Answer the clustered phrasings.** Add or expand sections that directly address the related queries in `evidence.phrasings` (these are real "People Also Ask"-style searches).
- **Update price context** with current `PriceIntel` framing (qualitative — "sits below its tracked 90-day average as of this writing"), never fabricated was/now numbers.
- **Strengthen internal links** to/from relevant reviews (site-relative `/posts/{slug}` links, both directions where natural).
- **ctr_fix:** if bundled, rewrite the **title / META_TITLE / META_DESCRIPTION** to be clearer and more compelling — truthfully. No clickbait, no claims the post doesn't support (AdSense misleading-content policy).

Save the refreshed content to `daily-drop/refresh-{SLUG}.md` (same field format as `/drop-write`, include `TARGET_QUERY: {query}`) so it's reviewable.

## Step 4 — Apply + resolve
1. Have the user open `/admin/posts/{id}/edit`, paste the refreshed body/title/meta, and **Save** (this bumps `updated_at`, refreshes the sitemap `lastmod`, and — with `SEARCH_PING_ENABLED` on prod — fires the IndexNow + Google sitemap pings automatically). Prefer the admin so Bryan reviews before it goes live.
2. Mark the opportunity resolved once applied:
   ```bash
   php artisan tinker --execute="App\Models\SearchOpportunity::find({OPP_ID})->update(['status'=>'done']);"
   ```
   (Or set it to `planned` first if the edit is queued for later. The next `search:mine` also auto-resolves it once the condition clears.)

## Done
Print, nothing more:
```
🔄 Refreshed — {post title} (targeting "{query}")
  Added: {1-line summary of what changed}
  Saved to daily-drop/refresh-{slug}.md → paste into /admin/posts/{id}/edit and Save
  Opportunity #{id} → done
```
