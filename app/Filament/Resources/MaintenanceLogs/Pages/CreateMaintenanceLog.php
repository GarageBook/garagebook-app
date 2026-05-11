<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Support\AnalyticsEventTracker;
use App\Services\DistanceUnitService;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceLog extends CreateRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(DistanceUnitService::class);
        $unit = $service->persistVehicleUnit(
            isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            $data['distance_unit'] ?? null
        );

        $data['km_reading'] = (int) round($service->toKilometers($data['km_reading'] ?? null, $unit, 0) ?? 0);
        $data['interval_km'] = $service->toKilometers($data['interval_km'] ?? null, $unit, 0);
        unset($data['distance_unit']);

        return $data;
    }

    protected function afterCreate(): void
    {
        app(AnalyticsEventTracker::class)->queueMaintenanceLogCreated($this->record);
        OptimizeMaintenanceLogMedia::dispatch($this->record->getKey());
    }
}
