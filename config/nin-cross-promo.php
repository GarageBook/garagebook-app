<?php

return [
    'lookup_url' => 'https://noginnederland.nl/api/v1/lookup',
    'enabled' => env('NIN_LOOKUP_ENABLED', true),
    'connect_timeout' => 1,
    'timeout' => 2,
    'match_ttl' => 12 * 60 * 60,
    'no_match_ttl' => 6 * 60 * 60,
    'unavailable_ttl' => 5 * 60,
    'last_match_ttl' => 7 * 24 * 60 * 60,
];
