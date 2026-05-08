#!/bin/bash

set -euo pipefail

cd /home/willem/garagebook
php artisan backup:run-disaster-recovery "$@"
