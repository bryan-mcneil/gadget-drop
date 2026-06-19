#!/usr/bin/env bash
#
# GadgetDrop — pull PRODUCTION database + images down to LOCAL dev.
#
# Run from the repo root in Git Bash (NOT on the server):
#
#   bash bin/sync-from-prod.sh           # prompts before clobbering local DB
#   bash bin/sync-from-prod.sh --yes     # skip the confirmation
#   bash bin/sync-from-prod.sh --db-only
#   bash bin/sync-from-prod.sh --images-only
#
# What it does:
#   1. SSH to prod, mysqldump the DB (creds read from the server's .env), gzip,
#      stream to a local temp file.
#   2. DROP + recreate the local DB and import the dump.
#   3. tar the prod storage/app/public image tree over SSH into local storage.
#   4. storage:link + clear caches locally.
#
# Nothing secret is stored: prod creds are read from the server .env at run time,
# local creds from your local .env.
set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# CONFIG — fill in your Hostinger SSH connection (the same one bin/deploy.sh uses)
# ─────────────────────────────────────────────────────────────────────────────
SSH_HOST="u746229724@195.35.39.157"          # e.g. u746229724@123.45.67.89  or  your saved Host alias
SSH_PORT="65002"                          # Hostinger SSH default; change if yours differs
SSH_KEY="~/.ssh/hostinger"                # private key for the connection (leave "" to use ssh-agent/password)
APP_ROOT="/home/u746229724/domains/gadgetdrop.tech/public_html"
# ─────────────────────────────────────────────────────────────────────────────

cd "$(dirname "$0")/.."                    # repo root
REPO_ROOT="$(pwd)"

DO_DB=1; DO_IMAGES=1; ASSUME_YES=0
for arg in "$@"; do
  case "$arg" in
    --db-only)     DO_IMAGES=0 ;;
    --images-only) DO_DB=0 ;;
    --yes|-y)      ASSUME_YES=1 ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

SSH_OPTS=(-p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)
if [ -n "$SSH_KEY" ]; then
  case "$SSH_KEY" in "~/"*) SSH_KEY="$HOME/${SSH_KEY#\~/}" ;; esac   # expand ~ (quoted ~ won't)
  case "$SSH_KEY" in /*|?:*) ;; *) SSH_KEY="$REPO_ROOT/$SSH_KEY" ;; esac
  [ -f "$SSH_KEY" ] || { echo "!! SSH key not found: $SSH_KEY" >&2; exit 1; }
  SSH_OPTS+=(-i "$SSH_KEY")
fi

if [[ "$SSH_HOST" == *CHANGE_ME* ]]; then
  echo "!! Edit bin/sync-from-prod.sh and set SSH_HOST first." >&2
  exit 1
fi

# Locate the local mysql client (MySQL96 bin is not on PATH on this box).
MYSQL_BIN="$(command -v mysql || true)"
if [ -z "$MYSQL_BIN" ]; then
  MYSQL_BIN="$(ls "/c/Program Files/MySQL/MySQL Server "*/bin/mysql.exe 2>/dev/null | head -1 || true)"
fi
[ -n "$MYSQL_BIN" ] || { echo "!! Could not find the local mysql client." >&2; exit 1; }

# Read a KEY from a .env file (strips surrounding quotes).
env_val() { sed -n "s/^$1=//p" "$2" | head -1 | sed 's/^["'\'']//; s/["'\'']$//'; }

L_DB="$(env_val DB_DATABASE "$REPO_ROOT/.env")"
L_USER="$(env_val DB_USERNAME "$REPO_ROOT/.env")"
L_PASS="$(env_val DB_PASSWORD "$REPO_ROOT/.env")"
L_HOST="$(env_val DB_HOST "$REPO_ROOT/.env")"; L_HOST="${L_HOST:-127.0.0.1}"
L_PORT="$(env_val DB_PORT "$REPO_ROOT/.env")"; L_PORT="${L_PORT:-3306}"

echo "Target LOCAL database: $L_DB on $L_HOST:$L_PORT"

# ── 1+2. Database ────────────────────────────────────────────────────────────
if [ "$DO_DB" = 1 ]; then
  if [ "$ASSUME_YES" != 1 ]; then
    read -r -p "This DROPS and replaces local DB '$L_DB' with prod data. Continue? [y/N] " ans
    [[ "$ans" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }
  fi

  DUMP="$(mktemp -t gd-prod-dump.XXXXXX)"
  trap 'rm -f "$DUMP"' EXIT
  echo "==> Dumping production database over SSH (read-only on prod)..."
  # Remote: read prod creds from the server's .env, dump via MYSQL_PWD, gzip on the server.
  # `set -o pipefail` so a mysqldump failure propagates instead of being masked by gzip
  # exiting 0 — otherwise we could wipe the local DB and import an empty file.
  remote_db='
    set -o pipefail
    cd '"$APP_ROOT"' || exit 1
    D=$(sed -n "s/^DB_DATABASE=//p" .env | head -1 | sed "s/^[\"'\'']//; s/[\"'\'']$//")
    U=$(sed -n "s/^DB_USERNAME=//p" .env | head -1 | sed "s/^[\"'\'']//; s/[\"'\'']$//")
    P=$(sed -n "s/^DB_PASSWORD=//p" .env | head -1 | sed "s/^[\"'\'']//; s/[\"'\'']$//")
    MYSQL_PWD="$P" mysqldump --single-transaction --no-tablespaces --routines --quick \
      --default-character-set=utf8mb4 -u"$U" "$D" | gzip -c
  '
  ssh "${SSH_OPTS[@]}" "$SSH_HOST" "$remote_db" > "$DUMP"

  # Validate the dump BEFORE touching the local DB (a bad/empty dump must not clobber it).
  if ! gunzip -t "$DUMP" 2>/dev/null; then
    echo "!! Dump is not a valid gzip stream (prod dump failed). Local DB left untouched." >&2
    exit 1
  fi
  head_sql="$(gunzip -c "$DUMP" 2>/dev/null | head -c 65536 || true)"
  case "$head_sql" in
    *"CREATE TABLE"*|*"MySQL dump"*) : ;;
    *) echo "!! Dump has no recognizable SQL. Local DB left untouched." >&2; exit 1 ;;
  esac
  echo "    dump OK ($(du -h "$DUMP" | cut -f1))"

  echo "==> Recreating and importing local database '$L_DB'..."
  MYSQL_PWD="$L_PASS" "$MYSQL_BIN" -h"$L_HOST" -P"$L_PORT" -u"$L_USER" \
    -e "DROP DATABASE IF EXISTS \`$L_DB\`; CREATE DATABASE \`$L_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  gunzip -c "$DUMP" | MYSQL_PWD="$L_PASS" "$MYSQL_BIN" --default-character-set=utf8mb4 \
    -h"$L_HOST" -P"$L_PORT" -u"$L_USER" "$L_DB"
  echo "    database imported."
fi

# ── 3. Images (storage/app/public) ───────────────────────────────────────────
if [ "$DO_IMAGES" = 1 ]; then
  echo "==> Pulling production images (storage/app/public) over SSH..."
  mkdir -p "$REPO_ROOT/storage/app/public"
  ssh "${SSH_OPTS[@]}" "$SSH_HOST" "cd '$APP_ROOT/storage/app' && tar czf - public" \
    | tar xzf - -C "$REPO_ROOT/storage/app"
  echo "    images synced."
fi

# ── 4. Local housekeeping ────────────────────────────────────────────────────
echo "==> Linking storage and clearing caches..."
php artisan storage:link >/dev/null 2>&1 || true
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan cache:clear   >/dev/null 2>&1 || true

echo "==> Done. Local now mirrors production (data + images)."
