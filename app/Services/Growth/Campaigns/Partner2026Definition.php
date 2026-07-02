<?php

namespace App\Services\Growth\Campaigns;

use App\Contracts\Growth\CampaignDiscoveryProvider;
use App\Services\Growth\Partner2026\CamperSpecialistDiscoveryProvider;
use App\Services\Growth\Partner2026\EnthusiastDetailingDiscoveryProvider;
use App\Services\Growth\Partner2026\MotorcycleAccessoriesDiscoveryProvider;
use App\Services\Growth\Partner2026\MotorcyclePartsDiscoveryProvider;
use App\Services\Growth\Partner2026\MotorcycleTireDiscoveryProvider;
use App\Services\Growth\Partner2026\OffroadDiscoveryProvider;
use App\Services\Growth\Partner2026\PerformanceDiscoveryProvider;
use App\Services\Growth\Partner2026\RestorationDiscoveryProvider;

class Partner2026Definition extends CampaignDefinition
{
    /**
     * @return array<int, CampaignDiscoveryProvider>
     */
    public function discoveryProviders(): array
    {
        return [
            app(MotorcycleTireDiscoveryProvider::class),
            app(MotorcyclePartsDiscoveryProvider::class),
            app(MotorcycleAccessoriesDiscoveryProvider::class),
            app(PerformanceDiscoveryProvider::class),
            app(RestorationDiscoveryProvider::class),
            app(OffroadDiscoveryProvider::class),
            app(CamperSpecialistDiscoveryProvider::class),
            app(EnthusiastDetailingDiscoveryProvider::class),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedSubtypes(): array
    {
        return [
            'motorcycle_tires',
            'motorcycle_parts_webshop',
            'motorcycle_accessories',
            'motorcycle_tuning',
            'tire_specialist',
            'detailing',
            'tuning',
            'suspension',
            'brakes',
            'exhaust',
            'parts_webshop',
            'oldtimer_restoration',
            'youngtimer_restoration',
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
