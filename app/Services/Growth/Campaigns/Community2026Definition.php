<?php

namespace App\Services\Growth\Campaigns;

use App\Contracts\Growth\CampaignDiscoveryProvider;
use App\Services\Growth\Community2026\BrandClubDiscoveryProvider;
use App\Services\Growth\Community2026\CamperClubDiscoveryProvider;
use App\Services\Growth\Community2026\ForumDiscoveryProvider;
use App\Services\Growth\Community2026\OldtimerClubDiscoveryProvider;
use App\Services\Growth\Community2026\TrackdayDiscoveryProvider;

class Community2026Definition extends CampaignDefinition
{
    /**
     * @return array<int, CampaignDiscoveryProvider>
     */
    public function discoveryProviders(): array
    {
        return [
            app(BrandClubDiscoveryProvider::class),
            app(OldtimerClubDiscoveryProvider::class),
            app(CamperClubDiscoveryProvider::class),
            app(TrackdayDiscoveryProvider::class),
            app(ForumDiscoveryProvider::class),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedSubtypes(): array
    {
        return [
            'oldtimer_club',
            'brand_club',
            'motorcycle_club',
            'car_club',
            'camper_club',
            'youngtimer_club',
            'trackday_community',
            'forum',
            'foundation',
            'association',
        ];
    }

    public function slug(): string
    {
        return 'community2026';
    }

    public function name(): string
    {
        return 'Community2026';
    }

    public function description(): string
    {
        return 'Merkclubs, oldtimerclubs, camperclubs, youngtimerclubs en andere voertuigcommunities.';
    }
}
