<?php

namespace App\Services\Growth\Partner2026;

class MotorcyclePartsDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'motorcycle_parts_webshop';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'motorparts-online.com',
            'motorparts.nl',
            'motoronderdelen.nl',
            'motoronderdelenshop.nl',
            'motoronderdelenmarkt.nl',
            'motorpartscenter.nl',
            'motoparts.nl',
            'motoparts-online.nl',
            'nr1motor.nl',
            'mymotor.nl',
            'motorcorner.nl',
            'motoroccasionparts.nl',
            'motorsloop.com',
            'motorsloop.nl',
            'boonstraparts.com',
            'baboon.eu',
            'motorparts4u.nl',
            'motorenonderdelen.nl',
            'motorfiets-onderdelen.nl',
            'motoronderdelenoutlet.nl',
            'japparts.nl',
            'cmsnl.com',
            'motorcyclespareparts.eu',
            'hocoparts.com',
            'athenaparts.nl',
            'motorkit.nl',

        ]);
    }
}
