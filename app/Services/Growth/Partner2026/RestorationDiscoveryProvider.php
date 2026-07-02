<?php

namespace App\Services\Growth\Partner2026;

class RestorationDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'oldtimer_restoration';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'oldtimerrestauratie.nl',
            'oldtimercentrum.nl',
            'classiccarrestoration.nl',
            'oldtimerbedrijf.nl',
            'oldtimer-service.nl',
            'oldtimerwerkplaats.nl',
            'klassiekercentrum.nl',
            'klassiekerrestauratie.nl',
            'classiccarspecialist.nl',
            'classiccars.nl',
            'youngtimercompany.nl',
            'youngtimerspecialist.nl',
            'youngtimerparts.nl',
            'youngtimerrestauratie.nl',
            'thegallerybrummen.nl',
            'stuurmanclassiccars.nl',
            'hofman.nl',
            'alberssportscars.com',
            'bartsparts.nl',
            'keverland.nl',
            'citroenklassiekers.nl',
            'volvoklassiekers.nl',
            'saabworld.nl',
            'bmwklassiek.nl',
            'alfaclassics.nl',
            'porscheclassiccenter.nl',
            'vanthullclassiccars.nl',
            'classicpark.nl',
            'dhcclassiccars.nl',
            'europeanclassiccars.nl',

        ]);
    }
}
