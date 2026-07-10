# /gd-health — Monthly (and post-deploy) maintenance sweep

Run a structured health check across the codebase, the data layer, and the new feature surfaces. Produce a 🟢/🟡/🔴 report with a concrete fix per non-green item. Read-only except where a check's fix is explicitly listed and Bryan approves it.

## Checks

### A. Code & suite
1. `php artisan test` — full count green (report the number; the baseline only ever goes up).
2. Local footgun scan: `bootstrap/cache/config.php` must NOT exist in the local checkout (DB-wipe guardrail — if present: `php artisan optimize:clear`, then investigate what created it).
3. `composer outdated --direct` + `npm outdated` — report only security-relevant or major drift; special attention to `laravel/mcp` (young package — read its changelog before ANY bump) and `laravel/dusk` (ChromeDriver pairing).
4. `composer audit` + `npm audit` — CVEs summarized, not dumped.
5. CI: latest runs on `main` green? Weekly Dusk run green? (`gh run list --limit 5` if gh is available; otherwise ask Bryan to eyeball Actions.)

### B. Schedule & budget guards
6. `php artisan schedule:list` — every task at minute `:00` (the hourly-cron contract). Any drift is 🔴.
7. Canopy budget state: the db-cache counters behind `prices:refresh` (daily_limit 3/day, monthly_budget 90/mo) — report consumption; 🔴 if the structural-$0 posture is threatened.
8. `storage/logs/laravel.log` — tail for the last 7 days' ERROR/CRITICAL; summarize patterns, not lines.

### C. Price-intelligence data health
9. Snapshot coverage: products with a published review lacking gate-passing history (≥2 snapshots spanning ≥14 days) — count + worst 5 by post view_count (these are surfaces showing no verdict to real traffic).
10. Price staleness: products with active price watches (once Plan 03 ships) or on /deals whose `price_checked_at` > 7 days — list for an `/admin/prices` pass.
11. `/deals` feed non-empty and rendering (curl the prod URL, 200 + at least one card — an empty feed is *allowed* (honesty) but worth knowing).

### D. Feature surfaces (as plans ship — skip silently if not yet deployed)
12. Voting: votes/day trend, global worth-vs-skip ratio, any single post with anomalous one-sided volume (possible griefing — eyeball, don't auto-act).
13. Watches: signups/week, verify rate (low = deliverability problem → check SPF/DKIM), alerts triggered, prune executing, table size.
14. MCP: usage counters (`mcp.calls.{tool}.{date}` keys, last 7 days) — calls/day per tool. Growth = the AI bet paying off; zero for a month = revisit directory listings. Throttle 429 patterns in logs.
15. Buy-or-wait: `ReleaseCycle::stale()` rows (`verified_at` > 6 months = 🟡, > 9 months = 🔴 — stale cycle advice is the site's biggest honesty risk). Any line whose product launched since last verify.
16. Truth reports: next sale event on the calendar? (June → Prime Day prep; October → BF prep: run Plan 05 §5.4 pre-event checks.)

### E. Production spot checks (prod URLs, not local)
17. `https://gadgetdrop.tech/sitemap.xml` 200; `/llms.txt` 200 and mentions current surfaces; `/mcp` initialize handshake OK (once Plan 04 ships); one review page renders verdict badge; Join the Drop submits (Livewire endpoint after any route:cache).

## Report format

- Header: date, overall 🟢/🟡/🔴.
- One line per check: `NN 🟢|🟡|🔴 — finding — fix (if not green)`.
- Footer: **Top 3 actions this month**, ranked by honesty-risk first, revenue second, hygiene third. Offer to file them into the relevant plan's Build Log or a todo.

Keep it under 40 lines. This command's job is a trustworthy monthly pulse, not an audit novel.
