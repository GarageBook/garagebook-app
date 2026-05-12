<?php

namespace App\Jobs;

use App\Models\TripLog;
use App\Services\Trips\TripParserManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessTripLogUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        public int $tripLogId,
    ) {}

    public function handle(TripParserManager $parserManager): void
    {
        $tripLog = TripLog::query()
            ->with('vehicle')
            ->find($this->tripLogId);

        if (! $tripLog) {
            return;
        }

        $tripLog->forceFill([
            'status' => TripLog::STATUS_PROCESSING,
            'failure_reason' => null,
        ])->save();

        try {
            $parsed = $parserManager->parseFromDisk(
                $tripLog->source_format,
                'local',
                $tripLog->source_file_path,
            );

            $tripLog->forceFill([
                'status' => TripLog::STATUS_PROCESSED,
                'failure_reason' => null,
                'distance_km' => $parsed->distanceKm,
                'duration_seconds' => $parsed->durationSeconds,
                'started_at' => $parsed->startedAt,
                'ended_at' => $parsed->endedAt,
                'points_count' => count($parsed->points),
                'bounds' => $parsed->bounds,
                'geojson' => json_encode($parsed->geojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'simplified_geojson' => json_encode($parsed->geojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stats' => $parsed->stats,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $tripLog->forceFill([
                'status' => TripLog::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ])->save();
        }
    }
}
