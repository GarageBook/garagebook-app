<?php

namespace App\Services\Growth\Partner2026;

class CamperSpecialistDiscoveryProvider extends AbstractPartnerDiscoveryProvider
{
    public function subtype(): string
    {
        return 'camper_specialist';
    }

    public function urls(): array
    {
        return $this->urlsForDomains([
            'camperbouw.nl',
            'camperbouw-holland.nl',
            'camperfixx.nl',
            'camperpunt.nl',
            'camperstore.nl',
            'camperpassie.nl',
            'campercentrum.nl',
            'campertechniek.nl',
            'camperonderhoud.nl',
            'camperparts.nl',
            'camperencaravanparts.nl',
            'camperaccessoires.nl',
            'camperhuis.nl',
            'buscamper.nl',
            'buscamperbouw.nl',
            'camperdreams.nl',
            'camperbouwservice.nl',
            'camperbouwers.nl',
            'camperplus.nl',
            'camperprofi.nl',
            'camper-service.nl',
            'campermakelaar.nl',
            'camperdeal.nl',
            'camper-totaal.nl',

        ]);
    }
}
