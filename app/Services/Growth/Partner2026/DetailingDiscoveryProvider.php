<?php

namespace App\Services\Growth\Partner2026;

class DetailingDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'detailing';
    }

    public function urls(): array
    {
        return $this->urlsForDomains(['carclean.nl', 'carclean.com', 'autodetailing.nl', 'autostyle.nl', 'showoffimports.nl', 'poetsbedrijf.nl', 'carwash.nl', 'detailingworld.nl', 'autoschoon.nl', 'autopoetsbedrijf.nl', 'glansgarant.nl', 'detailcars.nl']);
    }
}
