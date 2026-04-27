<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Support\MaintenanceMediaOptimizer;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceLog extends CreateRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function afterCreate(): void
    {
        app(MaintenanceMediaOptimizer::class)->optimizeLog($this->record);
    }
}
