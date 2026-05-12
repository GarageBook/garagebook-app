<?php

return [
    'tile_url' => env('MAP_TILE_URL', 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png'),
    'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; OpenStreetMap contributors &copy; CARTO'),
];
