<?php

return [
    'navigation_label' => 'Trips',
    'model_label' => 'Trip',
    'plural_model_label' => 'Trips',
    'form' => [
        'section_title' => 'Trip upload',
        'vehicle' => 'Voertuig',
        'title' => 'Titel',
        'description' => 'Beschrijving',
        'source_file' => 'GPX-bestand',
        'source_file_help' => 'Upload voorlopig een GPX-bestand. FIT, TCX en KML volgen later.',
        'status' => 'Status',
        'failure_reason' => 'Foutmelding',
        'processing_notice_label' => 'Verwerking',
        'processing_notice' => 'Grote routes worden op de achtergrond verwerkt. Je kunt na het uploaden gewoon verder werken.',
    ],
    'table' => [
        'vehicle' => 'Voertuig',
        'title' => 'Titel',
        'no_title' => 'Geen titel',
        'started_at' => 'Start',
        'distance' => 'Afstand',
        'duration' => 'Duur',
        'status' => 'Status',
        'points_count' => 'Punten',
        'created_at' => 'Aangemaakt',
        'not_processed' => 'Nog niet verwerkt',
    ],
    'infolist' => [
        'summary' => 'Tripoverzicht',
        'route' => 'Routekaart',
        'started_at' => 'Starttijd',
        'ended_at' => 'Eindtijd',
        'distance' => 'Afstand',
        'duration' => 'Duur',
        'points_count' => 'Puntenaantal',
        'source_file' => 'Origineel bestand',
        'failure_reason' => 'Foutmelding',
    ],
    'actions' => [
        'reprocess' => 'Opnieuw verwerken',
    ],
    'map' => [
        'pending' => 'De trip wordt nog verwerkt. Zodra de route klaar is verschijnt hier de kaart.',
        'failed' => 'Deze trip kon niet worden verwerkt.',
    ],
    'validation' => [
        'invalid_vehicle' => 'Selecteer een voertuig uit je eigen account.',
    ],
];
