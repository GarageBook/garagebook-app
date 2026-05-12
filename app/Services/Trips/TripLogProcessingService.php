<?php

namespace App\Services\Trips;

use App\Jobs\ProcessTripLogUpload;
use App\Models\TripLog;

class TripLogProcessingService
{
    public function reprocess(TripLog $tripLog): void
    {
        $tripLog->forceFill([
            'status' => TripLog::STATUS_PENDING,
            'failure_reason' => null,
        ])->save();

        ProcessTripLogUpload::dispatch($tripLog->getKey());
    }
}
