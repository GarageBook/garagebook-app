<?php

namespace App\Contracts\Growth;

interface CampaignDiscoveryProvider
{
    /**
     * @return list<string>
     */
    public function urls(): array;

    public function subtype(): string;
}
