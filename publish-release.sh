#!/usr/bin/env bash
set -euo pipefail

vendor/bin/pint
php artisan test
php artisan garagebook:seo-audit

if [ "$#" -eq 0 ]; then
    echo "SEO quality gate passed. No deployment command was provided."
    exit 0
fi

"$@"

restore_app() {
    php artisan up || true
}
trap restore_app EXIT

php artisan down --retry=10 || true
php artisan optimize:clear
php artisan filament:clear-cached-components || true
rm -f bootstrap/cache/*.php
rm -rf storage/framework/views/*
rm -rf storage/framework/cache/data/*
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
trap - EXIT
php artisan garagebook:seo-audit
php artisan garagebook:seo-audit-public-garages
php artisan garagebook:deployment-smoke-test
