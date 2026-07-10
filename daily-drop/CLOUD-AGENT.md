# GadgetDrop Overnight Content Agent

Instructions for the **scheduled Claude cloud routine** that generates each day's content drafts before Bryan's morning. You run in a cloud sandbox with a clone of this repo. You have **no database access** — you only produce files, which is all the pipeline needs. Bryan lands your output with `/morning`.

## Environment expectations
- Repo cloned, working tree clean, on `main`.
- Secrets (configure as routine env/secrets, NOT in the repo): `API_URL=https://gadgetdrop.tech` and `GADGETDROP_API_KEY` — used by `/drop-research` for the reviewed-products dedupe. If missing, research degrades gracefully ("API unavailable, no dedupe") per that skill's contract; continue anyway.
- `php` is available (`bin/daily-drop-build.php` is plain PHP, no Laravel boot, no vendor/ needed).

## Daily task

1. **Clean slate.** Delete any leftover `daily-drop/research.md`, `product-*.md`, `tip-*.md`, `news-*.md`, `output.json` from previous days (they were merged already; a fresh branch means a fresh day).

2. **Read the SEO brief.** If `daily-drop/seo-brief.md` exists (committed by `/morning` from prod's Search Console/Bing data), read it before choosing topics. It re-ranks WHICH topics have demonstrated demand — the review/refresh/tip/news candidates and an avoid list. Prefer its candidates **only when they pass each skill's value gate**; fall back to editorial judgment (and say so in `research.md`) when the brief is missing or its generated date is > 3 days old. The brief never changes the cadence below.

3. **Determine today's mix** from the cadence table in `.claude/commands/daily-drop.md`:
   - Every day: one review.
   - Mon/Thu: plus one tech news post. Tue/Sat: plus one tech tip.

4. **Run the pipeline steps**, following each skill file in `.claude/commands/` exactly:
   1. `/drop-research` (drop-research.md) → `daily-drop/research.md`
   2. `/drop-write 1` (drop-write.md) → `daily-drop/product-1.md` (use product 2 only if the primary is a dud)
   3. `/drop-tip` or `/drop-news` on their days → `daily-drop/tip-1.md` / `news-1.md`
   4. `/drop-assemble` (drop-assemble.md): run `php bin/daily-drop-build.php` — **it must exit 0**. Fix hard errors it names. Fix warnings you can fix with targeted edits (banned phrase, em dash, length); leave judgment calls and note them.

5. **Deliver as a PR.**
   - **Branch:** commit on the sandbox's own working branch (the auto-generated `claude/…` branch you start on). The cloud GitHub proxy **only lets you push to that branch** — do NOT try to create or push a `drop/YYYY-MM-DD` branch; the push will be rejected. The branch *name* doesn't matter; the PR title and commit subject are the contract `/morning` keys off.
   - Commit all generated `daily-drop/` files with message `Drop YYYY-MM-DD: {review title}` (+ tip/news title if present).
   - Open a PR **titled exactly `Drop YYYY-MM-DD`** against `main` (this title is how `/morning` finds the drop — it must be exact). PR body: the build script's per-post summary, remaining warnings (or "no warnings"), and one line per post: type, title, category, word count.
   - Do NOT merge the PR. Do NOT touch anything outside `daily-drop/`.
   - **Your PR must pass CI** (GitHub Actions runs `tests`/`assets`/`style` on every PR). A content-only PR cannot trip it — if your PR goes red, you touched code you shouldn't have; revert to `daily-drop/` files only. Do not "fix" failing tests or styles — that is never your job.

## Hard rules
- Follow `CONTENT-GUIDELINES.md` — especially the honesty rules (no first-hand-testing claims), no in-body Amazon links, and the 6 fixed categories. These exist because the site was denied AdSense once already.
- Never scrape Amazon pages (Associates policy violation). Product facts come from web search results, coverage, and spec sheets.
- If a step fails unrecoverably (no viable product found, build errors you cannot fix), open the PR anyway with whatever validated, prefix the title `[INCOMPLETE]`, and explain what's missing in the body — Bryan's `/morning` can fill the gap locally.
