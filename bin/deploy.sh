#!/usr/bin/env bash
#
# GadgetDrop production deploy — run on the Hostinger server over SSH:
#
#   bash bin/deploy.sh
#
# Prereqs: assets were built locally (npm run build) and committed, so git pull
# delivers code + public/build together. This script is idempotent.
set -euo pipefail

PHP=/opt/alt/php84/usr/bin/php
APP_ROOT=/home/u746229724/domains/gadgetdrop.tech/public_html

cd "$APP_ROOT"

echo "==> Pulling latest code (fast-forward only)"
git pull --ff-only

echo "==> Installing composer dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running migrations"
$PHP artisan migrate --force

echo "==> Rebuilding config/route/view/event caches"
$PHP artisan optimize

echo "==> Backfilling responsive image variants (idempotent)"
$PHP artisan images:optimize

echo "==> Done. Verify: $PHP artisan about | grep -A6 Cache"