<?php

namespace App\Services\Trips;

use App\Data\Trips\ParsedTripData;
use RuntimeException;

class TripParserManager
{
    public function __construct(
        private readonly GpxTripParser $gpxTripParser,
    ) {}

    public function parseFromDisk(string $format, string $disk, string $path): ParsedTripData
    {
        return match ($format) {
            'gpx' => $this->gpxTripParser->parseFromDisk($disk, $path),
            default => throw new RuntimeException("Onbekend tripformaat [{$format}]."),
        };
    }
}
