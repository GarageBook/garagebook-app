<?php

namespace App\Services\Growth\Partner2026;

class MotorcycleAccessoriesDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'motorcycle_accessories';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'motoraccessoires.nl',
            'motorkledingcenter.nl',
            'mkcmoto.com',
            'bikeroutfit.nl',
            'motorkledingstore.nl',
            'motorkledingoutlet.nl',
            'motorhelmen.nl',
            'helmonline.nl',
            'rad.eu',
            'chromeburner.nl',
            'motorkledingvoordeel.nl',
            'motorcorner.nl',
            'motorsportlifestyle.nl',
            'motozoom.nl',
            'bikesupply.nl',
            'louis.nl',
            'motorrijdershop.nl',
            'adventuremotorcycle.nl',
            'advdesigns.nl',
            'motoadventurestore.nl',
            'motortrailer.nl',
            'topkoffer.nl',
            'motorkoffers.nl',
            'motorhoes.nl',
            'motoport.nl',

        ]);
    }
}
