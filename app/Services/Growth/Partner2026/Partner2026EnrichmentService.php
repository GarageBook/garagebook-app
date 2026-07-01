<?php

namespace App\Services\Growth\Partner2026;

use App\Services\Growth\Campaigns\CampaignEnrichmentService as GenericEnrichmentService;
use App\Services\Growth\Campaigns\Partner2026Definition;

class Partner2026EnrichmentService
{
    public function __construct(
        private readonly GenericEnrichmentService $service,
        private readonly Partner2026Definition $definition,
    ) {}

    /**
     * @return array{scanned:int,auto_found:int,suggested_found:int,still_missing:int,ready_top_50:array<int, array{name:string,website:?string,email:?string,confidence:?int}>}
     */
    public function enrich(?int $limit = null): array
    {
        return $this->service->enrich($this->definition, $limit);
    }
}
