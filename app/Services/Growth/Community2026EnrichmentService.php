<?php

namespace App\Services\Growth;

use App\Services\Growth\Campaigns\CampaignEnrichmentService as GenericEnrichmentService;
use App\Services\Growth\Campaigns\Community2026Definition;

class Community2026EnrichmentService
{
    public function __construct(
        private readonly GenericEnrichmentService $service,
        private readonly Community2026Definition $definition,
    ) {}

    /**
     * @return array{scanned:int,auto_found:int,suggested_found:int,still_missing:int,ready_top_50:array<int, array{name:string,website:?string,email:?string,confidence:?int}>}
     */
    public function enrich(?int $limit = null): array
    {
        return $this->service->enrich($this->definition, $limit);
    }
}
