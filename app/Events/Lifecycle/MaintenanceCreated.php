<?php

namespace App\Events\Lifecycle;

use App\Models\MaintenanceLog;
use App\Models\User;

class MaintenanceCreated
{
    public function __construct(public readonly MaintenanceLog $maintenanceLog) {}

    public function user(): ?User
    {
        return $this->maintenanceLog->vehicle()->first()?->user()->first();
    }
}
