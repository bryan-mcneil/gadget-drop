# /morning — Bryan's One Daily Command: Merge, Import, QA, Checklist

## Context
The scheduled cloud agent (see `daily-drop/CLOUD-AGENT.md`) opens a PR each morning containing the day's generated content files. This command lands that content as **draft posts in PRODUCTION** (drafts must live where review/images/publish happen: the live admin), then hands Bryan a review checklist. Target: the whole command runs in ~5 minutes.

> **Branch naming:** the cloud sandbox's GitHub proxy restricts pushes to the session's own auto-generated working branch (e.g. `claude/laughing-pasteur-80foow`), so the agent **cannot** push a `drop/YYYY-MM-DD` branch. The stable identifier is the **PR title `Drop YYYY-MM-DD`** and the commit subject `Drop YYYY-MM-DD: …`. Detect by those, never by a `drop/` branch name.

SSH connection values (host, port, key, APP_ROOT) live at the top of `bin/sync-from-prod.sh` — read them from there, never hard-code.

## Steps

1. **Find today's drop.** `git fetch origin --prune`, then locate today's PR/branch by **title**, not branch name (see the Branch-naming note above):
   - If `gh` is available: `gh pr list --state open --search "Drop {today YYYY-MM-DD} in:title"` → grab its head branch (`--json headRefName`).
   - No `gh` (e.g. local Windows box): find the remote branch whose tip commit is today's drop —
     ```bash
     git for-each-ref --format='%(refname:short)|%(subject)' refs/remotes/origin \
       | grep -iE "\|Drop {today YYYY-MM-DD}"
     ```
     The match's `refname` (typically `origin/claude/…`) is the branch to merge. Sanity-check it actually changed `daily-drop/output.json`: `git show --stat {ref} -- daily-drop/output.json`.
   - **Found** → note the branch ref, continue.
   - **Not found** → the cloud agent didn't run or failed. Report that, then offer to run the pipeline locally right now (`/drop-research` → `/drop-write 1` → `/drop-tip` or `/drop-news` per the cadence in `/daily-drop` → `/drop-assemble`), then continue from step 3.

2. **Check CI, then merge it.** First, the PR's CI status (`gh pr checks {ref}` — accepts the head branch from step 1 — or the checks section on the PR page): all three jobs (`tests`, `assets`, `style`) must be green. **Red or still running → stop and show Bryan** — a content-only PR cannot trip CI, so a red run means the agent touched code it shouldn't have; there is no enforced branch protection (private repo, GitHub Free), so this check IS the gate. No `gh`? Note that CI was unverified and continue — the local re-validate (step 3) and dry-run (step 4) still stand. Then merge: confirm the working tree is clean (stop and report if not). Check out `main`, then merge the branch from step 1 into it: `git merge --ff-only {ref}` (the cloud branch is one commit on top of main, so this fast-forwards); if ff-only is refused because main advanced, fall back to `git merge --no-ff {ref}`. Prefer `gh pr merge --merge` if `gh` is available. Do not force anything; if the merge isn't clean, stop and show the conflict.

3. **Re-validate locally.** Run `php bin/daily-drop-build.php`. Exit 0 required; surface every warning to Bryan (the cloud agent should have listed them in the PR body — re-check anyway). Hard error → fix per `/drop-assemble` rules, re-run.

4. **QA gate.** Run `php artisan posts:import --dry-run` locally (parses + resolves everything, writes nothing). Then scan the day's `daily-drop/*.md` bodies for testing-claim phrases (the list in `config/content.php`) — same check `content:flag-claims` applies to published posts, applied before import instead.

5. **Ship to production.**
   - Push main: `git push origin main`.
   - Import on the server (values from `bin/sync-from-prod.sh`):
     ```bash
     ssh -p {SSH_PORT} -i {SSH_KEY} {SSH_HOST} "cd {APP_ROOT} && git pull --ff-only && /opt/alt/php84/usr/bin/php artisan posts:import daily-drop/output.json"
     ```
     (Content-only pull — no composer/migrate/optimize needed. If code changed too, run `bash bin/deploy.sh` on the server instead.)
   - **SSH unavailable?** Fallback: tell Bryan to open `https://gadgetdrop.tech/admin/daily-drop` and paste the contents of `daily-drop/output.json` into the Import page. Same importer, same result.

6. **Refresh the SEO brief (Search Intel).** Regenerate tomorrow's demand brief from prod's Search Console/Bing data and commit it so the overnight cloud agent reads it from the repo. Values from `bin/sync-from-prod.sh`:
   ```bash
   ssh -p {SSH_PORT} -i {SSH_KEY} {SSH_HOST} "cd {APP_ROOT} && /opt/alt/php84/usr/bin/php artisan search:brief" > daily-drop/seo-brief.md
   ```
   Then commit + push it: `git add daily-drop/seo-brief.md && git commit -m "Update SEO brief {date}" && git push origin main`. If Search Intel isn't configured yet (Phase 0 not done), the brief will say "No opportunities yet" — commit it anyway; the pipeline falls back to editorial judgment. SSH unavailable → skip this step (the pipeline degrades gracefully).

7. **Checklist.** Print exactly this, filled in (pull the SEO line from `ssh … "cd {APP_ROOT} && … artisan search:status --compact"`):
   ```
   ☀️ Drop {date} imported — {N} draft(s) on production:
     {type}  {title}  → https://gadgetdrop.tech/admin/posts ({edit link if id known})
   Build warnings: {list or "none"}
   {SEO: N open opportunities · M recent post(s) not confirmed indexed — from search:status --compact}

   Your pass (~45 min):
   1. Review each draft: fact-check prices/claims, tone pass
   2. Add images (hero + product shots) via the post form uploader
   3. Publish when happy
   4. /admin/prices — quick stale-price pass
   5. /admin/seo — glance at open opportunities + any unindexed posts
   ```

## Notes
- Never publish automatically. Everything imports as drafts; Bryan is always the publisher.
- If today's drop was already merged/imported (branch merged, posts exist), say so and just print the checklist — the command must be safe to re-run.
