<?php

namespace App\Services\Growth\Campaigns;

use App\Contracts\Growth\CampaignDiscoveryProvider;
use App\Services\Growth\Partner2026\DetailingDiscoveryProvider;
use App\Services\Growth\Partner2026\LifestyleDiscoveryProvider;
use App\Services\Growth\Partner2026\PartsDiscoveryProvider;
use App\Services\Growth\Partner2026\TireSpecialistDiscoveryProvider;
use App\Services\Growth\Partner2026\TuningDiscoveryProvider;

class Partner2026Definition extends CampaignDefinition
{
    /**
     * @return array<int, CampaignDiscoveryProvider>
     */
    public function discoveryProviders(): array
    {
        return [
            app(TireSpecialistDiscoveryProvider::class),
            app(DetailingDiscoveryProvider::class),
            app(TuningDiscoveryProvider::class),
            app(PartsDiscoveryProvider::class),
            app(LifestyleDiscoveryProvider::class),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedSubtypes(): array
    {
        return [
            'tire_specialist',
            'detailing',
            'tuning',
            'suspension',
            'brakes',
            'parts_webshop',
            'motorcycle_accessories',
            'oldtimer_restoration',
            'custom_shop',
            'camper_specialist',
            '4x4_specialist',
        ];
    }

    public function slug(): string
    {
        return 'partner2026';
    }

    public function name(): string
    {
        return 'Partner2026';
    }

    public function description(): string
    {
        return 'Gespecialiseerde bedrijven rondom onderhoud, onderdelen, banden, detailing, tuning, vering, remmen, oldtimers, campers, 4x4 en motoraccessoires.';
    }
}
