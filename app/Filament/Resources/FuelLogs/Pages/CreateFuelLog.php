<?php

namespace App\Filament\Resources\FuelLogs\Pages;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use App\Support\AnalyticsEventTracker;
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

        return $this->normalizeEntryData($data);
    }

    protected function afterCreate(): void
    {
        app(AnalyticsEventTracker::class)->queueFuelEntryCreated($this->record);
    }

    private function normalizeEntryData(array $data): array
    {
        $vehicle = isset($data['vehicle_id'])
            ? Vehicle::query()->where('user_id', auth()->id())->find((int) $data['vehicle_id'])
            : null;

        if ($vehicle?->isElectric()) {
            $data['entry_type'] = FuelLog::ENTRY_TYPE_CHARGE;
        } elseif (! $vehicle?->isPhev()) {
            $data['entry_type'] = FuelLog::ENTRY_TYPE_FUEL;
        } else {
            $data['entry_type'] = FuelLog::normalizeEntryType($data['entry_type'] ?? null);
        }

        if (! in_array($data['entry_type'], [FuelLog::ENTRY_TYPE_FUEL, FuelLog::ENTRY_TYPE_COMBINED], true)) {
            $data['fuel_liters'] = null;
            $data['price_per_liter'] = null;
        }

        if (! in_array($data['entry_type'], [FuelLog::ENTRY_TYPE_CHARGE, FuelLog::ENTRY_TYPE_COMBINED], true)) {
            $data['energy_kwh'] = null;
            $data['price_per_kwh'] = null;
            $data['charge_type'] = null;
            $data['notes'] = null;
        }

        if (($data['charge_type'] ?? null) === FuelLog::CHARGE_TYPE_HOME && blank($data['price_per_kwh'] ?? null) && $vehicle?->home_kwh_rate !== null) {
            $data['price_per_kwh'] = (string) $vehicle->home_kwh_rate;
        }

        if (blank($data['total_cost'] ?? null)) {
            $data['total_cost'] = $this->calculateTotalCost($data);
        }

        return $data;
    }

    private function calculateTotalCost(array $data): ?float
    {
        $fuelCost = filled($data['fuel_liters'] ?? null) && filled($data['price_per_liter'] ?? null)
            ? (float) $data['fuel_liters'] * (float) $data['price_per_liter']
            : null;
        $chargeCost = filled($data['energy_kwh'] ?? null) && filled($data['price_per_kwh'] ?? null)
            ? (float) $data['energy_kwh'] * (float) $data['price_per_kwh']
            : null;

        if ($fuelCost === null && $chargeCost === null) {
            return null;
        }

        return round((float) $fuelCost + (float) $chargeCost, 2);
    }
}
