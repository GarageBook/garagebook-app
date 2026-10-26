<?php

namespace App\Observers;

use App\Events\Lifecycle\MaintenanceCreated;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;

class MaintenanceLogObserver
{
    public function created(MaintenanceLog $maintenanceLog): void
    {
        event(new MaintenanceCreated($maintenanceLog));
    }

    public function updated(MaintenanceLog $maintenanceLog): void
    {
        if (! $maintenanceLog->wasChanged('vehicle_id')) {
            return;
        }

        Vehicle::query()
            ->whereKey($maintenanceLog->getRawOriginal('vehicle_id'))
            ->where('is_public', true)
            ->first()?->touch();
    }

    public function deleted(MaintenanceLog $maintenanceLog): void
    {
        if ($maintenanceLog->vehicle?->is_public) {
            $maintenanceLog->vehicle->touch();
        }
    }
}
