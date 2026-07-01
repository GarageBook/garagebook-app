<?php

namespace App\Services\Growth\Partner2026;

class TireSpecialistDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'tire_specialist';
    }

    public function urls(): array
    {
        return $this->urlsForDomains(['kwikfit.nl', 'profile.nl', 'euromaster.nl', 'banden.nl', 'bandenconcurrent.nl', 'bandenmarkt.nl', 'bandenspecialist.nl', 'bandenexpert.nl', 'bandenservice.nl', 'autobandenmarkt.nl', 'tyreservice.nl', 'bandenleader.nl']);
    }
}
