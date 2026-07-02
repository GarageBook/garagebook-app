<?php

namespace App\Services\Growth\Partner2026;

class PerformanceDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'motorcycle_tuning';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'rica.nl',
            'beekautoracing.nl',
            'dvxperformance.nl',
            'jdengineering.nl',
            'vagtechniek.nl',
            'ziptuning.nl',
            'br-performance.nl',
            'tuningservice.nl',
            'chiptuningexperience.nl',
            'bullpower.nl',
            'autosportcompany.nl',
            'fasttech.nl',
            'aceparts.nl',
            'uitlaten.com',
            'uitlaatservice.nl',
            'epsuitlaten.nl',
            'skytune.nl',
            'mad-exhausts.nl',
            'hurricane-exhausts.nl',
            'intraxracing.nl',
            'ohlins.nl',
            'hyperpro.com',
            'hk-suspension.nl',
            'brembo-store.nl',
            'remservice.nl',
            'racecracks.nl',
            'tenkateracingproducts.com',
            'motoporthipolito.nl',
            'startrick.nl',
            'sparks-online.nl',

        ]);
    }
}
