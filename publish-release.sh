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

php artisan optimize:clear
php artisan filament:clear-cached-components
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan garagebook:seo-audit
php artisan garagebook:seo-audit-public-garages
php artisan garagebook:deployment-smoke-test
