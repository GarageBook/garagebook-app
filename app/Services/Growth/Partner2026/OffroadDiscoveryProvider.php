<?php

namespace App\Services\Growth\Partner2026;

class OffroadDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return '4x4_specialist';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            '4x4pro.nl',
            '4x4winkel.nl',
            'offroadcenter.nl',
            'terrain4x4.nl',
            '4x4parts.nl',
            '4x4offroad.nl',
            'offroadshop.nl',
            'jeeparts.nl',
            'jeepcenter.nl',
            'landroverparts.nl',
            'landroverclassicparts.nl',
            'lrparts.nl',
            'roversland.nl',
            'budgetparts.nl',
            'terrainparts.nl',
            'offroadreifen.nl',
            '4wdshop.nl',
            'toyota4x4parts.nl',
            'suzuki4x4parts.nl',
            'landrover-service.nl',
            'jeepspecialist.nl',
            'offroadcentrum.nl',
            'adventure4x4.nl',
            'overlandparts.nl',

        ]);
    }
}
