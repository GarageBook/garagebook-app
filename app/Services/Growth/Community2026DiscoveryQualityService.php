<?php

namespace App\Services\Growth;

use App\Data\Growth\DiscoveryResult;
use App\Services\Growth\Campaigns\CampaignQualityFilter;
use App\Services\Growth\Campaigns\Community2026Definition;

class Community2026DiscoveryQualityService
{
    public function __construct(
        private readonly CampaignQualityFilter $quality,
        private readonly Community2026Definition $definition,
    ) {}

    public function assess(DiscoveryResult $result): DiscoveryResult
    {
        return $this->quality->assess($result, $this->definition);
    }
}
