<?php

namespace App\Services\Growth\Partner2026;

class Partner2026Paths
{
    public function seedUrlsPath(): string
    {
        return storage_path('app/imports/partner2026_seed_urls.txt');
    }

    public function discoveredCsvPath(): string
    {
        return storage_path('app/imports/partner2026_discovered.csv');
    }

    public function rejectedCsvPath(): string
    {
        return storage_path('app/imports/partner2026_rejected.csv');
    }

    public function importCsvPath(): string
    {
        return storage_path('app/imports/partner2026.csv');
    }

    public function reportPath(): string
    {
        return storage_path('app/imports/partner2026_report.json');
    }
}
