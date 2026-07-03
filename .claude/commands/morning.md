# /morning — Bryan's One Daily Command: Merge, Import, QA, Checklist

## Context
The scheduled cloud agent (see `daily-drop/CLOUD-AGENT.md`) opens a PR each morning on branch `drop/YYYY-MM-DD` containing the day's generated content files. This command lands that content as **draft posts in PRODUCTION** (drafts must live where review/images/publish happen: the live admin), then hands Bryan a review checklist. Target: the whole command runs in ~5 minutes.

SSH connection values (host, port, key, APP_ROOT) live at the top of `bin/sync-from-prod.sh` — read them from there, never hard-code.

## Steps

1. **Find today's drop.** `git fetch origin`, look for branch `drop/{today YYYY-MM-DD}` (and its PR via `gh pr list --head drop/{today}` if gh is available).
   - **Branch exists** → continue.
   - **No branch** → the cloud agent didn't run or failed. Report that, then offer to run the pipeline locally right now (`/drop-research` → `/drop-write 1` → `/drop-tip` or `/drop-news` per the cadence in `/daily-drop` → `/drop-assemble`), then continue from step 3.

2. **Merge it.** Confirm the working tree is clean (stop and report if not). Merge the drop branch into `main` (prefer merging the PR with `gh pr merge --merge`; otherwise `git merge --ff-only origin/drop/{today}` after checking out main). Do not force anything; if the merge isn't clean, stop and show the conflict.

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

6. **Checklist.** Print exactly this, filled in:
   ```
   ☀️ Drop {date} imported — {N} draft(s) on production:
     {type}  {title}  → https://gadgetdrop.tech/admin/posts ({edit link if id known})
   Build warnings: {list or "none"}

   Your pass (~45 min):
   1. Review each draft: fact-check prices/claims, tone pass
   2. Add images (hero + product shots) via the post form uploader
   3. Publish when happy
   4. /admin/prices — quick stale-price pass
   ```

## Notes
- Never publish automatically. Everything imports as drafts; Bryan is always the publisher.
- If today's drop was already merged/imported (branch merged, posts exist), say so and just print the checklist — the command must be safe to re-run.
