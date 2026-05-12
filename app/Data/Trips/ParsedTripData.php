<?php

namespace App\Data\Trips;

readonly class ParsedTripData
{
    /**
     * @param  array<int, array{lat: float, lng: float, elevation: float|null, recorded_at: string|null}>  $points
     * @param  array{north: float, south: float, east: float, west: float}|null  $bounds
     * @param  array<string, mixed>  $geojson
     * @param  array<string, mixed>  $stats
     */
    public function __construct(
        public array $points,
        public ?string $startedAt,
        public ?string $endedAt,
        public ?float $distanceKm,
        public ?int $durationSeconds,
        public ?array $bounds,
        public array $geojson,
        public array $stats,
    ) {}
}
