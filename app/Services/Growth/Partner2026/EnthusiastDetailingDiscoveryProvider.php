<?php

namespace App\Services\Growth\Partner2026;

class EnthusiastDetailingDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'detailing';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'carclean.com',
            'carclean.nl',
            'waxworld.nl',
            'carcareproducts.nl',
            'carcarefreaks.nl',
            'detailing.nl',
            'detailingworld.nl',
            'autodetailing.nl',
            'detailingshop.nl',
            'detailingstore.nl',
            'detailingproducts.nl',
            'showoffimports.nl',
            'glansgarant.nl',
            'detailcars.nl',
            'customcarcleaning.nl',
            'elitecarcare.nl',
            'exclusivecarcare.nl',
            'autopoetsbedrijf.nl',
            'poetsbedrijf.nl',
            'carpolish.nl',
            'nanolex.nl',
            'gyeonquartz.nl',
            'detailed.nl',
            'shineandprotect.nl',
            'cardetailingcenter.nl',
            'detailingcrew.nl',

        ]);
    }
}
