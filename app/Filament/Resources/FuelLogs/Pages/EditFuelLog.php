<?php

namespace App\Filament\Resources\FuelLogs\Pages;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Services\DistanceUnitService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelLog extends EditRecord
{
    protected static string $resource = FuelLogResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $service = app(DistanceUnitService::class);
        $unit = $service->resolveForVehicleId($data['vehicle_id'] ?? null);

        $data['distance_unit'] = $unit;
        $data['odometer_km'] = $service->fromKilometers($data['odometer_km'] ?? null, $unit, 1);
        $data['distance_km'] = $service->fromKilometers($data['distance_km'] ?? null, $unit, 1);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('fuel.actions.delete'))
                ->modalHeading(__('fuel.delete_modal.heading'))
                ->modalDescription(__('fuel.delete_modal.description')),
        ];
    }
}
