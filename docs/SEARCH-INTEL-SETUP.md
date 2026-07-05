# Search Intel — Setup & Bring-Up

One-time setup for the Search Intel layer (GSC + Bing + IndexNow). The code is
built and no-ops without credentials, so this is purely the human "Phase 0" plus
the prod bring-up. Plan of record: `search-intel.md`. Day-to-day runbook:
`docs/OPERATIONS.md` / `/morning`.

> **Secrets never go in git.** The Google JSON key lives in `storage/app/keys/`
> (gitignored) and is uploaded to the server by hand; all other values live in
> `.env` (gitignored). Nothing here is committed.

---

## The one thing everyone gets wrong: roles

**The service account needs ZERO Google Cloud IAM roles.** Search Console API
access is authorized by Search Console's own *Users and permissions* list, not by
GCP IAM. The service account is just an identity (email + key file); you authorize
it by adding its email as an **Owner** on the Search Console property.

GCP side needs only: (1) the API enabled, (2) a JSON key.
Search Console side needs: the account added as **Owner** (Owner, not Full,
because we submit sitemaps via API — see the table below).

| API we call | Command | Min Search Console permission |
|---|---|---|
| Search Analytics | `search:sync` | Full |
| URL Inspection | `search:inspect` | Full |
| **Sitemaps submit** | publish ping (`PostObserver`) | **Owner** |

---

## 1. Google Cloud — service account

1. **Create a project** (console.cloud.google.com) → e.g. `gadgetdrop-search`.
2. **Enable the API** → *APIs & Services → Library* → **"Google Search Console API"** → Enable.
3. **Create the service account** → *IAM & Admin → Service Accounts → Create*:
   - Name `search-intel` → email becomes `search-intel@<project>.iam.gserviceaccount.com`.
   - **"Grant this service account access to project" → leave EMPTY** (no role).
   - Skip "grant users access" → Done.
4. **Create the key** → the account → **Keys → Add Key → Create new key → JSON**.
   Downloads a `.json` containing `client_email`, `private_key`, `project_id`.

## 2. Search Console — authorize it

search.google.com/search-console → **Settings → Users and permissions → Add user**
→ paste the service account **`client_email`** → permission **Owner**.

## 3. `GSC_PROPERTY` — match the property type exactly

In Search Console *Settings*, check the property type. The env string must match
byte-for-byte:
- **Domain property** (preferred): `sc-domain:gadgetdrop.tech`
- **URL-prefix property**: `https://gadgetdrop.tech/` (trailing slash)

## 4. Bing Webmaster (API key, no roles)

bing.com/webmasters → **Import from Google Search Console** (one click, verifies
instantly) → **Settings → API Access → Generate API Key**. One per-user key covers
all sites. The API `siteUrl` must match how Bing stores the site (usually the
URL-prefix form); `BING_SITE_URL` defaults to `SEARCH_SITE_URL`.

## 5. IndexNow key (no account)

The key file *is* the verification. Generate:
```bash
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
```
Set `INDEXNOW_KEY`. The `/indexnow.txt` route serves it; pings pass `keyLocation`.

## 6. Key placement + env

Upload the Google JSON outside the webroot and lock it down (on the server):
```bash
mkdir -p storage/app/keys
# upload gsc-service-account.json into storage/app/keys/
chmod 600 storage/app/keys/gsc-service-account.json
```
`storage/app/keys/` is gitignored. Set env (prod `.env`; locally too if you want
to pull data on your machine):
```dotenv
GSC_PROPERTY=sc-domain:gadgetdrop.tech
GSC_CREDENTIALS_PATH=storage/app/keys/gsc-service-account.json   # match the uploaded filename
BING_WEBMASTER_API_KEY=...
INDEXNOW_KEY=...
SEARCH_PING_ENABLED=true        # PROD ONLY — leave off/absent locally so dev never pings
# optional (already default to these):
# SEARCH_SITE_URL=https://gadgetdrop.tech
# BING_SITE_URL=https://gadgetdrop.tech/
```
After editing `.env` on prod: `php artisan config:clear` (or `optimize`).

## 7. Deploy the feature + backfill

The Search Intel code ships like any other change (build local → commit →
`bash bin/deploy.sh` on the server, which runs `migrate --force` to create the 7
tables). Then, once per install, backfill 16 months on the server:
```bash
/opt/alt/php84/usr/bin/php artisan search:sync --backfill=480
```

## 8. Verify (`php artisan tinker` on the server)

```php
app(\App\Services\GoogleSearchConsoleService::class)->token();        // non-null string
app(\App\Services\GoogleSearchConsoleService::class)->searchAnalytics([
  'startDate' => '2026-06-20', 'endDate' => '2026-06-27', 'dimensions' => ['query'],
]);                                                                    // rows array
app(\App\Services\BingWebmasterService::class)->rankAndTrafficStats(); // array
```
`curl https://gadgetdrop.tech/indexnow.txt` → returns the key. Then `search:mine`,
and open `/admin/seo`.

## 9. How it runs after bring-up

- **Schedule (UTC, minute :00):** `search:sync` 07:00 → `search:mine` 08:00 →
  `search:inspect` 09:00.
- **Publish pings:** `PostObserver` fires IndexNow + sitemap resubmit on publish /
  slug change (gated by `SEARCH_PING_ENABLED`).
- **Brief:** `/morning` regenerates `daily-drop/seo-brief.md` over SSH and commits
  it; the overnight agent + `/drop-research|tip|news` read it. `/drop-refresh`
  (Sundays) acts on decay/striking-distance opportunities.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `token()` returns null | property unset, or JSON path wrong/unreadable (check `chmod 600`, path resolves from app root) |
| `403 PERMISSION_DENIED` on a real call | service account not added as **Owner** on the *exact* property, or `GSC_PROPERTY` string/type mismatch |
| `SERVICE_DISABLED` | "Google Search Console API" not enabled on the project |
| Key creation blocked | a GCP **org policy** (`iam.disableServiceAccountKeyCreation`); a personal project won't have it |
| Quota-project permission error (rare) | grant the account `roles/serviceusage.serviceUsageConsumer` |
| Empty data right after backfill | GSC finalized data lags ~2–3 days; recent days fill in over time |
