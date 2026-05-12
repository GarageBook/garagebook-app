<?php

namespace App\Services\Trips;

use Carbon\CarbonImmutable;

class TripStatsCalculator
{
    public function calculateDistanceKm(array $points): float
    {
        $distanceKm = 0.0;

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            $previous = $points[$index - 1];
            $current = $points[$index];

            $distanceKm += $this->calculateSegmentDistanceKm(
                $previous['lat'],
                $previous['lng'],
                $current['lat'],
                $current['lng'],
            );
        }

        return $distanceKm;
    }

    public function calculateSegmentDistanceKm(float $startLat, float $startLng, float $endLat, float $endLng): float
    {
        $earthRadiusKm = 6371.0;

        $deltaLat = deg2rad($endLat - $startLat);
        $deltaLng = deg2rad($endLng - $startLng);

        $startLatRadians = deg2rad($startLat);
        $endLatRadians = deg2rad($endLat);

        $a = sin($deltaLat / 2) ** 2
            + cos($startLatRadians) * cos($endLatRadians) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    public function calculateDurationSeconds(?string $startedAt, ?string $endedAt): ?int
    {
        if (! $startedAt || ! $endedAt) {
            return null;
        }

        $start = CarbonImmutable::parse($startedAt);
        $end = CarbonImmutable::parse($endedAt);

        if ($end->lessThan($start)) {
            return null;
        }

        return $start->diffInSeconds($end);
    }

    /**
     * @param  array<int, array{lat: float, lng: float, elevation: float|null, recorded_at: string|null}>  $points
     * @return array{north: float, south: float, east: float, west: float}|null
     */
    public function calculateBounds(array $points): ?array
    {
        if ($points === []) {
            return null;
        }

        $latitudes = array_column($points, 'lat');
        $longitudes = array_column($points, 'lng');

        return [
            'north' => max($latitudes),
            'south' => min($latitudes),
            'east' => max($longitudes),
            'west' => min($longitudes),
        ];
    }

    /**
     * @param  array<int, array{lat: float, lng: float, elevation: float|null, recorded_at: string|null}>  $points
     * @return array<string, mixed>
     */
    public function buildGeoJsonFeature(array $points, array $properties = []): array
    {
        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => array_map(function (array $point): array {
                    $coordinates = [$point['lng'], $point['lat']];

                    if ($point['elevation'] !== null) {
                        $coordinates[] = $point['elevation'];
                    }

                    return $coordinates;
                }, $points),
            ],
        ];
    }
}
