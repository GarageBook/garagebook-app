<?php

namespace App\Services\Growth\Community2026;

use App\Contracts\Growth\CampaignDiscoveryProvider;

interface CommunityDiscoveryProvider extends CampaignDiscoveryProvider
{
    /**
     * @return list<string>
     */
    public function urls(): array;

    public function subtype(): string;
}
