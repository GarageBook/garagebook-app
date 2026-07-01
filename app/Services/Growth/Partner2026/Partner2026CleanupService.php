<?php

namespace App\Services\Growth\Partner2026;

use App\Services\Growth\Campaigns\CampaignCleanupService as GenericCleanupService;
use App\Services\Growth\Campaigns\Partner2026Definition;
use Illuminate\Support\Collection;

class Partner2026CleanupService
{
    public function __construct(
        private readonly GenericCleanupService $service,
        private readonly Partner2026Definition $definition,
    ) {}

    /**
     * @return array<string, int>
     */
    public function cleanup(): array
    {
        return $this->service->cleanup($this->definition);
    }

    public function attentionRecords(int $limit = 20): Collection
    {
        return $this->service->attentionRecords($this->definition, $limit);
    }

    public function campaignSlug(): string
    {
        return $this->definition->slug();
    }
}
