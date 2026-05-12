<?php

namespace App\Services;

use App\Models\FuelLog;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FuelConsumptionService
{
    public const UNIT_L_PER_100_KM = 'l_per_100km';
    public const UNIT_KM_PER_LITER = 'km_per_liter';
    public const KILOMETERS_PER_MILE = 1.609344;
    public const LITERS_PER_US_GALLON = 3.785411784;

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

    public function calculateLitersPer100Km(?float $distanceKm, ?float $fuelLiters): ?float
    {
        return $this->calculateAverage($distanceKm, $fuelLiters, self::UNIT_L_PER_100_KM);
    }

    public function calculateKilometersPerLiter(?float $distanceKm, ?float $fuelLiters): ?float
    {
        return $this->calculateAverage($distanceKm, $fuelLiters, self::UNIT_KM_PER_LITER);
    }

    public function calculateRoundedKilometersPerLiterRatio(?float $distanceKm, ?float $fuelLiters): ?int
    {
        $kilometersPerLiter = $this->calculateKilometersPerLiter($distanceKm, $fuelLiters);

        if ($kilometersPerLiter === null) {
            return null;
        }

        return max(1, (int) round($kilometersPerLiter));
    }

    public function convertKilometersToMiles(?float $kilometers): ?float
    {
        if ($kilometers === null) {
            return null;
        }

        return $kilometers / self::KILOMETERS_PER_MILE;
    }

    public function convertMilesToKilometers(?float $miles): ?float
    {
        if ($miles === null) {
            return null;
        }

        return $miles * self::KILOMETERS_PER_MILE;
    }

    public function convertLitersToUsGallons(?float $liters): ?float
    {
        if ($liters === null) {
            return null;
        }

        return $liters / self::LITERS_PER_US_GALLON;
    }

    public function calculateMilesPerUsGallon(?float $distanceKm, ?float $fuelLiters): ?float
    {
        $distanceMiles = $this->convertKilometersToMiles($distanceKm);
        $gallons = $this->convertLitersToUsGallons($fuelLiters);

        if (! $distanceMiles || ! $gallons || $distanceMiles <= 0 || $gallons <= 0) {
            return null;
        }

        return $distanceMiles / $gallons;
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

    public function getTrendVehicleOptionsForUser(int $userId): array
    {
        return Vehicle::query()
            ->where('user_id', $userId)
            ->whereHas('fuelLogs')
            ->latest()
            ->get()
            ->mapWithKeys(fn (Vehicle $vehicle) => [
                (string) $vehicle->id => $vehicle->nickname ?: ($vehicle->brand . ' ' . $vehicle->model),
            ])
            ->all();
    }

    public function resolveDefaultTrendVehicleId(int $userId): ?int
    {
        $vehicleIds = Vehicle::query()
            ->where('user_id', $userId)
            ->whereHas('fuelLogs')
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            return null;
        }

        return FuelLog::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->orderByDesc('fuel_date')
            ->orderByDesc('id')
            ->value('vehicle_id');
    }

    public function getRecentConsumptionTrendForUser(int $userId, ?string $unit, ?int $vehicleId = null, int $limit = 8): array
    {
        $unit = $this->normalizeUnit($unit);
        $limit = max(1, $limit);

        $query = FuelLog::query()
            ->whereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('user_id', $userId))
            ->where('distance_km', '>', 0)
            ->where('fuel_liters', '>', 0)
            ->with('vehicle')
            ->orderByDesc('fuel_date')
            ->orderByDesc('id');

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        $logs = $query
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return [
            'labels' => $logs
                ->map(fn (FuelLog $log) => CarbonImmutable::parse($log->fuel_date)->translatedFormat('d M'))
                ->all(),
            'averages' => $logs
                ->map(fn (FuelLog $log) => round(
                    $this->calculateAverage((float) $log->distance_km, (float) $log->fuel_liters, $unit) ?? 0,
                    2
                ))
                ->all(),
        ];
    }

    public function getConsumptionTrendForVehicle(int $userId, int $vehicleId, int $limit = 12): array
    {
        $limit = max(1, $limit);

        $logs = FuelLog::query()
            ->where('vehicle_id', $vehicleId)
            ->whereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('user_id', $userId))
            ->where('distance_km', '>', 0)
            ->where('fuel_liters', '>', 0)
            ->orderByDesc('fuel_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return [
            'labels' => $logs
                ->map(fn (FuelLog $log) => CarbonImmutable::parse($log->fuel_date)->translatedFormat('d M'))
                ->all(),
            'liters_per_100_km' => $logs
                ->map(fn (FuelLog $log) => round(
                    $this->calculateLitersPer100Km((float) $log->distance_km, (float) $log->fuel_liters) ?? 0,
                    2
                ))
                ->all(),
            'mpg_us' => $logs
                ->map(fn (FuelLog $log) => round(
                    $this->calculateMilesPerUsGallon((float) $log->distance_km, (float) $log->fuel_liters) ?? 0,
                    1
                ))
                ->all(),
            'has_enough_points' => $logs->count() >= 2,
        ];
    }
}
