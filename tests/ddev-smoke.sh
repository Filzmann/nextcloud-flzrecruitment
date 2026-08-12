#!/usr/bin/env bash
set -euo pipefail

ddev_project="${RECR_DDEV_PROJECT:-$(cd "$(dirname "$0")/../../nextcloud-dev" && pwd)}"
base_url="${RECR_BASE_URL:-https://nextcloud-dev.ddev.site}"

run_smoke() {
    local file="$1"
    (cd "$ddev_project" && ddev exec -d /var/www/html/html env RECR_BASE_URL="$base_url" php -r \
        "define('OC_CONSOLE', true); require '/var/www/html/html/custom_apps/adrecruitment/tests/integration/${file}';")
}

run_smoke VerticalSliceSmoke.php
run_smoke AuthenticatedPageSmoke.php
