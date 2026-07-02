<?php

namespace App\Services\Growth\Partner2026;

class MotorcycleTireDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'motorcycle_tires';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'motorbandenservice.nl',
            'motorbandenquickservice.nl',
            'motorbanden.nl',
            'motorbandenexpert.nl',
            'motorbandenzaak.nl',
            'motorbandenexpress.nl',
            'motorbandencentrum.nl',
            'motorbandenhal.nl',
            'motorbandenspecialist.nl',
            'bikerbanden.nl',
            'motorbandenconcurrent.nl',
            'motorbandenoutlet.nl',
            'motortyre.nl',
            'motorbandenshop.nl',
            'bandenvoorjemotor.nl',
            'motorbandenamsterdam.nl',
            'motorbandenrotterdam.nl',
            'motorbandendenhaag.nl',
            'motorbandenutrecht.nl',
            'motorbandenbrabant.nl',
            'motobanden.nl',
            'tovami.com',
            'motoportwormerveer.nl',
            'motorserviceamersfoort.nl',

        ]);
    }
}
