<?php

namespace App\Observers;

use App\Events\Lifecycle\MaintenanceCreated;
use App\Models\MaintenanceLog;

class MaintenanceLogObserver
{
    public function created(MaintenanceLog $maintenanceLog): void
    {
        event(new MaintenanceCreated($maintenanceLog));
    }
}
