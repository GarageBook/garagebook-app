<?php

namespace App\Services\Growth\Community2026;

class TrackdayDiscoveryProvider implements CommunityDiscoveryProvider
{
    public function subtype(): string
    {
        return 'trackday_community';
    }

    public function urls(): array
    {
        return [
            'https://www.ttcircuit.com',
            'https://www.circuitzandvoort.nl',
            'https://www.rszmotorsport.nl',
            'https://www.racecracks.nl',
            'https://www.raceparkmeppen.com',
            'https://www.trackdays.nl',
            'https://www.trackday.nl',
            'https://www.tracktime.nl',
            'https://www.motorcircuittraining.nl',
            'https://www.motorrijvaardigheidstraining.nl',
            'https://www.prodrive-training.nl',
            'https://www.gp-elite.nl',
            'https://www.driving-fun.com',
            'https://www.driving-fun.nl',
            'https://www.driftsport.nl',
            'https://www.driftcursus.nl',
            'https://www.slipcursus.nl',
            'https://www.rijvaardigheidstraining.nl',
            'https://www.circuitdag.nl',
            'https://www.circuitdagen.nl',
            'https://www.trackdayevents.nl',
            'https://www.raceplanet.nl',
            'https://www.bleekemolensraceplanet.nl',
            'https://www.raceschool.nl',
            'https://www.racing-school.nl',
            'https://www.motoaction.nl',
            'https://www.motorsportschool.nl',
            'https://www.motorsportlifestyle.nl',
            'https://www.racecursus.nl',
            'https://www.race-academy.nl',
            'https://www.raceexperience.nl',
            'https://www.drivingexperience.nl',
            'https://www.autosportacademy.nl',
            'https://www.dnrt.nl',
            'https://www.knaf.nl',
            'https://www.acnn.nl',
            'https://www.harc.nl',
            'https://www.vrijrijden.nl',
            'https://www.racefreaks.nl',
            'https://www.racingnews365.nl',
            'https://www.autosport.nl',
            'https://www.nederlandserallysport.nl',
            'https://www.rallysport.nl',
            'https://www.rallyclub.nl',
            'https://www.4x4rijvaardigheid.nl',
            'https://www.4x4club.nl',
            'https://www.offroadchallenge.nl',
            'https://www.enduro.nl',
            'https://www.motocrossplanet.nl',
            'https://www.mxworld.be',
        ];
    }
}
