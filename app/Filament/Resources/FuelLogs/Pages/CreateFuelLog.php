<?php

namespace App\Filament\Resources\FuelLogs\Pages;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Services\DistanceUnitService;
use Filament\Resources\Pages\CreateRecord;

class CreateFuelLog extends CreateRecord
{
    protected static string $resource = FuelLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['vehicle_id'] ?? null) && request()->filled('vehicle_id')) {
            $data['vehicle_id'] = (int) request()->integer('vehicle_id');
        }

        $service = app(DistanceUnitService::class);
        $unit = $service->persistVehicleUnit(
            isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            $data['distance_unit'] ?? null
        );

        $data['odometer_km'] = $service->toKilometers($data['odometer_km'] ?? null, $unit, 1);
        $data['distance_km'] = $service->toKilometers($data['distance_km'] ?? null, $unit, 1);
        unset($data['distance_unit']);

        return $data;
    }
}
