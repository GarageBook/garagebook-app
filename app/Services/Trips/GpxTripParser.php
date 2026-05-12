<?php

namespace App\Services\Trips;

use App\Data\Trips\ParsedTripData;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;

class GpxTripParser
{
    public const MAX_SOURCE_FILE_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly TripStatsCalculator $statsCalculator,
    ) {}

    public function parseFromDisk(string $disk, string $path): ParsedTripData
    {
        $size = Storage::disk($disk)->size($path);

        if (! is_int($size) || $size <= 0) {
            throw new RuntimeException('Het originele GPX-bestand kon niet worden gelezen.');
        }

        if ($size > self::MAX_SOURCE_FILE_BYTES) {
            throw new RuntimeException('Het GPX-bestand is te groot om veilig te verwerken.');
        }

        $contents = Storage::disk($disk)->get($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new RuntimeException('Het originele GPX-bestand kon niet worden gelezen.');
        }

        return $this->parse($contents);
    }

    public function parse(string $contents): ParsedTripData
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($contents);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Het GPX-bestand kon niet worden gelezen.');
        }

        $points = [];

        foreach ($xml->xpath('//*[local-name()="trkpt"]') ?: [] as $trackPoint) {
            if (! $trackPoint instanceof SimpleXMLElement) {
                continue;
            }

            $latitude = $this->floatOrNull((string) ($trackPoint['lat'] ?? ''));
            $longitude = $this->floatOrNull((string) ($trackPoint['lon'] ?? ''));

            if ($latitude === null || $longitude === null) {
                continue;
            }

            $recordedAt = null;
            $timeNodes = $trackPoint->xpath('./*[local-name()="time"]');

            if ($timeNodes !== false && isset($timeNodes[0])) {
                $time = trim((string) $timeNodes[0]);
                $recordedAt = $time !== '' ? $time : null;
            }

            $elevation = null;
            $elevationNodes = $trackPoint->xpath('./*[local-name()="ele"]');

            if ($elevationNodes !== false && isset($elevationNodes[0])) {
                $elevation = $this->floatOrNull((string) $elevationNodes[0]);
            }

            $points[] = [
                'lat' => $latitude,
                'lng' => $longitude,
                'elevation' => $elevation,
                'recorded_at' => $recordedAt,
            ];
        }

        if (count($points) < 2) {
            throw new RuntimeException('Het GPX-bestand bevat minder dan 2 geldige trackpunten.');
        }

        $startedAt = collect($points)
            ->pluck('recorded_at')
            ->filter()
            ->sort()
            ->first();

        $endedAt = collect($points)
            ->pluck('recorded_at')
            ->filter()
            ->sort()
            ->last();

        $distanceKm = $this->statsCalculator->calculateDistanceKm($points);
        $durationSeconds = $this->statsCalculator->calculateDurationSeconds($startedAt, $endedAt);
        $bounds = $this->statsCalculator->calculateBounds($points);
        $stats = [
            'points_count' => count($points),
            'has_timestamps' => $startedAt !== null && $endedAt !== null,
        ];

        $geojson = $this->statsCalculator->buildGeoJsonFeature($points, [
            'source_format' => 'gpx',
            'points_count' => count($points),
        ]);

        return new ParsedTripData(
            points: $points,
            startedAt: $startedAt,
            endedAt: $endedAt,
            distanceKm: $distanceKm,
            durationSeconds: $durationSeconds,
            bounds: $bounds,
            geojson: $geojson,
            stats: $stats,
        );
    }

    private function floatOrNull(string $value): ?float
    {
        $value = trim($value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
