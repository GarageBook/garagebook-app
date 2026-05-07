<?php

namespace App\Services;

use App\Models\FuelLog;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class FuelConsumptionService
{
    public const UNIT_L_PER_100_KM = 'l_per_100km';
    public const UNIT_KM_PER_LITER = 'km_per_liter';

    public function getSupportedUnits(): array
    {
        return [
            self::UNIT_L_PER_100_KM => 'L/100 km',
            self::UNIT_KM_PER_LITER => 'km/l',
        ];
    }

    public function normalizeUnit(?string $unit): string
    {
        return array_key_exists($unit, $this->getSupportedUnits())
            ? $unit
            : self::UNIT_L_PER_100_KM;
    }

    public function calculateAverage(?float $distanceKm, ?float $fuelLiters, ?string $unit): ?float
    {
        $unit = $this->normalizeUnit($unit);

        if (! $distanceKm || ! $fuelLiters || $distanceKm <= 0 || $fuelLiters <= 0) {
            return null;
        }

        if ($unit === self::UNIT_KM_PER_LITER) {
            return $distanceKm / $fuelLiters;
        }

        return ($fuelLiters / $distanceKm) * 100;
    }

    public function formatAverage(?float $distanceKm, ?float $fuelLiters, ?string $unit): string
    {
        $unit = $this->normalizeUnit($unit);
        $average = $this->calculateAverage($distanceKm, $fuelLiters, $unit);

        if ($average === null) {
            return 'Onbekend';
        }

        return number_format($average, 2, ',', '.') . ' ' . $this->getSupportedUnits()[$unit];
    }

    public function calculateTotalCost(?float $fuelLiters, ?float $pricePerLiter): ?float
    {
        if ($fuelLiters === null || $pricePerLiter === null) {
            return null;
        }

        return round($fuelLiters * $pricePerLiter, 2);
    }

    public function getPreviousOdometerForVehicle(int $vehicleId, string $fuelDate, ?int $ignoreFuelLogId = null): ?float
    {
        $query = FuelLog::query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('fuel_date', '<=', $fuelDate)
            ->whereNotNull('odometer_km');

        if ($ignoreFuelLogId) {
            $query->whereKeyNot($ignoreFuelLogId);
        }

        return $query
            ->orderByDesc('fuel_date')
            ->orderByDesc('id')
            ->value('odometer_km');
    }

    public function suggestDistanceKm(int $vehicleId, ?string $fuelDate, ?float $odometerKm, ?int $ignoreFuelLogId = null): ?float
    {
        if (! $fuelDate || $odometerKm === null) {
            return null;
        }

        $previousOdometer = $this->getPreviousOdometerForVehicle($vehicleId, $fuelDate, $ignoreFuelLogId);

        if ($previousOdometer === null || $odometerKm <= $previousOdometer) {
            return null;
        }

        return round($odometerKm - $previousOdometer, 1);
    }

    public function getVehicleSummary(Vehicle $vehicle, ?string $unit): array
    {
        $distanceKm = (float) $vehicle->fuel_logs_sum_distance_km;
        $fuelLiters = (float) $vehicle->fuel_logs_sum_fuel_liters;
        $knownCost = $vehicle->fuelLogs
            ->filter(fn (FuelLog $fuelLog) => $fuelLog->price_per_liter !== null)
            ->sum(fn (FuelLog $fuelLog) => (float) $fuelLog->fuel_liters * (float) $fuelLog->price_per_liter);

        return [
            'distance_km' => $distanceKm,
            'fuel_liters' => $fuelLiters,
            'total_cost' => $knownCost,
            'average_label' => $this->formatAverage($distanceKm, $fuelLiters, $unit),
        ];
    }

    public function getVehiclesForUserWithFuelStats(int $userId): Collection
    {
        return Vehicle::query()
            ->where('user_id', $userId)
            ->whereHas('fuelLogs')
            ->withSum('fuelLogs', 'distance_km')
            ->withSum('fuelLogs', 'fuel_liters')
            ->with(['fuelLogs' => fn ($query) => $query
                ->select('id', 'vehicle_id', 'fuel_liters', 'price_per_liter')
                ->latest('fuel_date')
                ->latest('id')])
            ->latest()
            ->get();
    }
}
