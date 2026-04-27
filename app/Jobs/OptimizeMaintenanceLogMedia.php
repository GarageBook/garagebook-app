<?php

namespace App\Jobs;

use App\Models\MaintenanceLog;
use App\Support\MaintenanceMediaOptimizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class OptimizeMaintenanceLogMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $maintenanceLogId,
    ) {}

    public function handle(MaintenanceMediaOptimizer $optimizer): void
    {
        $log = MaintenanceLog::query()->find($this->maintenanceLogId);

        if (! $log) {
            return;
        }

        $optimizer->optimizeLog($log);
    }
}
