<?php

return [
    'ga4' => [
        // The Measurement ID must come from environment config so app and property stay aligned per deployment.
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'linker_domains' => [
            'garagebook.nl',
            'app.garagebook.nl',
        ],
    ],
];
