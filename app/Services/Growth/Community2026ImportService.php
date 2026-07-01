<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Services\Growth\Campaigns\CampaignImportService as GenericImportService;
use App\Services\Growth\Campaigns\Community2026Definition;

class Community2026ImportService
{
    public function __construct(
        private readonly GenericImportService $service,
        private readonly Community2026Definition $definition,
    ) {}

    /**
     * @return array{created:int, updated:int, skipped:int, enriched:int, imported:int}
     */
    public function importPath(string $path): array
    {
        return $this->service->importPath($this->definition, $path);
    }

    public function campaign(): GrowthCampaign
    {
        return $this->service->campaign($this->definition);
    }

    public function campaignSlug(): string
    {
        return $this->definition->slug();
    }
}
