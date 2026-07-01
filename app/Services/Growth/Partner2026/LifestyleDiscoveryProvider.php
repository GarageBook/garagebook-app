<?php

namespace App\Services\Growth\Partner2026;

class LifestyleDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'oldtimer_restoration';
    }

    public function urls(): array
    {
        return $this->urlsForDomains(['nkc.nl', 'campercontact.com', 'campervoordeel.nl', 'camperstore.nl', 'oldtimercentrum.nl', 'oldtimerrestauratie.nl', '4x4winkel.nl', '4x4pro.nl', 'offroadcenter.nl', 'customcars.nl', 'classiccarrestoration.nl', 'camperplaats.nl']);
    }
}
