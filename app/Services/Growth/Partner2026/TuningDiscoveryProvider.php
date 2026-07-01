<?php

namespace App\Services\Growth\Partner2026;

class TuningDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'tuning';
    }

    public function urls(): array
    {
        return $this->urlsForDomains(['rpmracing.nl', 'chiptuning.nl', 'tuningservice.nl', 'tuningparts.nl', 'driven.nl', 'dutchperformance.nl', 'powerplus.nl', 'speedcentre.nl', 'racesquare.nl', 'autowereld.nl', 'showoffimports.nl', 'autostyle.nl']);
    }
}
