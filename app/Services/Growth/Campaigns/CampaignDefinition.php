<?php

namespace App\Services\Growth\Campaigns;

use App\Contracts\Growth\CampaignDiscoveryProvider;

abstract class CampaignDefinition
{
    /**
     * @return array<int, CampaignDiscoveryProvider>
     */
    abstract public function discoveryProviders(): array;

    /**
     * @return array<int, string>
     */
    abstract public function allowedSubtypes(): array;

    abstract public function slug(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    public function seedLabel(): string
    {
        return $this->name();
    }

    public function seedUrlsPath(): string
    {
        return storage_path('app/imports/'.$this->slug().'_seed_urls.txt');
    }

    public function discoveredCsvPath(): string
    {
        return storage_path('app/imports/'.$this->slug().'_discovered.csv');
    }

    public function rejectedCsvPath(): string
    {
        return storage_path('app/imports/'.$this->slug().'_rejected.csv');
    }

    public function importCsvPath(): string
    {
        return storage_path('app/imports/'.$this->slug().'.csv');
    }

    public function reportPath(): string
    {
        return storage_path('app/imports/'.$this->slug().'_report.json');
    }

    public function readyReportPath(): string
    {
        return storage_path('app/imports/'.$this->slug().'_ready_report.json');
    }

    public function discoveryLimit(): int
    {
        return 500;
    }

    public function fetchLimit(): int
    {
        return 75;
    }
}
