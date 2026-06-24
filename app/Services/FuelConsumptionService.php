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

        return number_format($average, 2, ',', '.').' '.$this->getSupportedUnits()[$unit];
    }

    public function calculateTotalCost(?float $amount, ?float $unitPrice): ?float
    {
        if ($amount === null || $unitPrice === null) {
            return null;
        }

        return round($amount * $unitPrice, 2);
    }

    public function calculateKwhPer100Km(?float $distanceKm, ?float $energyKwh): ?float
    {
        if (! $distanceKm || ! $energyKwh || $distanceKm <= 0 || $energyKwh <= 0) {
            return null;
        }

        return ($energyKwh / $distanceKm) * 100;
    }

    public function formatKwhPer100Km(?float $distanceKm, ?float $energyKwh): string
    {
        $average = $this->calculateKwhPer100Km($distanceKm, $energyKwh);

        if ($average === null) {
            return 'Onbekend';
        }

        return number_format($average, 2, ',', '.').' kWh/100 km';
    }

    public function calculateCostPerKm(?float $distanceKm, ?float $totalCost): ?float
    {
        if (! $distanceKm || $totalCost === null || $distanceKm <= 0 || $totalCost < 0) {
            return null;
        }

        return $totalCost / $distanceKm;
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
        $logs = $vehicle->fuelLogs;
        $distanceKm = (float) ($vehicle->fuel_logs_sum_distance_km ?? $logs->sum(fn (FuelLog $log) => (float) ($log->distance_km ?? 0)));
        $fuelLiters = (float) ($vehicle->fuel_logs_sum_fuel_liters ?? $logs->sum(fn (FuelLog $log) => (float) ($log->fuel_liters ?? 0)));
        $energyKwh = (float) ($vehicle->fuel_logs_sum_energy_kwh ?? $logs->sum(fn (FuelLog $log) => (float) ($log->energy_kwh ?? 0)));
        $fuelCost = $logs
            ->filter(fn (FuelLog $fuelLog) => $fuelLog->isFuelEntry())
            ->sum(function (FuelLog $fuelLog): float {
                if ($fuelLog->entry_type === FuelLog::ENTRY_TYPE_FUEL && $fuelLog->total_cost !== null) {
                    return (float) $fuelLog->total_cost;
                }

                return (float) ($this->calculateTotalCost(
                    $fuelLog->fuel_liters !== null ? (float) $fuelLog->fuel_liters : null,
                    $fuelLog->price_per_liter !== null ? (float) $fuelLog->price_per_liter : null
                ) ?? 0);
            });
        $chargeCost = $logs
            ->filter(fn (FuelLog $fuelLog) => $fuelLog->isChargeEntry())
            ->sum(function (FuelLog $fuelLog): float {
                if ($fuelLog->entry_type === FuelLog::ENTRY_TYPE_CHARGE && $fuelLog->total_cost !== null) {
                    return (float) $fuelLog->total_cost;
                }

                return (float) ($this->calculateTotalCost(
                    $fuelLog->energy_kwh !== null ? (float) $fuelLog->energy_kwh : null,
                    $fuelLog->price_per_kwh !== null ? (float) $fuelLog->price_per_kwh : null
                ) ?? 0);
            });
        $evStats = $this->getElectricStatsForVehicle($vehicle);
        $totalKnownCost = $logs->sum(fn (FuelLog $fuelLog) => (float) ($fuelLog->knownTotalCost() ?? 0));

        return [
            'distance_km' => $distanceKm,
            'fuel_liters' => $fuelLiters,
            'energy_kwh' => $energyKwh,
            'total_cost' => $fuelCost + $chargeCost,
            'fuel_cost' => $fuelCost,
            'charge_cost' => $chargeCost,
            'average_label' => $this->formatAverage($distanceKm, $fuelLiters, $unit),
            'average_kwh_per_100_km' => $evStats['average_kwh_per_100_km'],
            'average_price_per_kwh' => $energyKwh > 0 && $chargeCost > 0 ? $chargeCost / $energyKwh : null,
            'cost_per_km' => $this->calculateCostPerKm($distanceKm, $totalKnownCost),
            'seasonal' => $evStats['seasonal'],
        ];
    }

    public function getElectricStatsForVehicle(Vehicle $vehicle): array
    {
        $intervals = $this->getElectricIntervalsForVehicle($vehicle);
        $distanceKm = $intervals->sum('distance_km');
        $energyKwh = $intervals->sum('energy_kwh');
        $cost = $intervals->sum('cost');
        $summer = $intervals->filter(fn (array $interval) => in_array((int) CarbonImmutable::parse($interval['date'])->month, [4, 5, 6, 7, 8, 9], true));
        $winter = $intervals->reject(fn (array $interval) => in_array((int) CarbonImmutable::parse($interval['date'])->month, [4, 5, 6, 7, 8, 9], true));
        $summerAverage = $this->averageKwhForIntervals($summer);
        $winterAverage = $this->averageKwhForIntervals($winter);

        return [
            'distance_km' => $distanceKm,
            'energy_kwh' => $energyKwh,
            'cost' => $cost,
            'average_kwh_per_100_km' => $this->calculateKwhPer100Km($distanceKm > 0 ? $distanceKm : null, $energyKwh > 0 ? $energyKwh : null),
            'cost_per_km' => $this->calculateCostPerKm($distanceKm > 0 ? $distanceKm : null, $cost),
            'seasonal' => [
                'summer_kwh_per_100_km' => $summerAverage,
                'winter_kwh_per_100_km' => $winterAverage,
                'difference_percentage' => $summerAverage !== null && $summerAverage > 0 && $winterAverage !== null
                    ? (($winterAverage - $summerAverage) / $summerAverage) * 100
                    : null,
            ],
        ];
    }

    public function getElectricIntervalsForVehicle(Vehicle $vehicle): Collection
    {
        $logs = $vehicle->relationLoaded('fuelLogs')
            ? $vehicle->fuelLogs
            : $vehicle->fuelLogs()->get();

        $previousOdometer = null;

        return $logs
            ->filter(fn (FuelLog $log) => $log->isChargeEntry() && $log->energy_kwh !== null && $log->odometer_km !== null)
            ->sortBy([
                ['fuel_date', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(function (FuelLog $log) use (&$previousOdometer): ?array {
                $currentOdometer = (float) $log->odometer_km;

                if ($previousOdometer === null) {
                    $previousOdometer = $currentOdometer;

                    return null;
                }

                $distanceKm = $currentOdometer - $previousOdometer;

                if ($distanceKm <= 0) {
                    return null;
                }

                $previousOdometer = $currentOdometer;

                return [
                    'date' => $log->fuel_date,
                    'distance_km' => $distanceKm,
                    'energy_kwh' => (float) $log->energy_kwh,
                    'cost' => (float) ($log->knownTotalCost() ?? 0),
                ];
            })
            ->filter()
            ->values();
    }

    private function averageKwhForIntervals(Collection $intervals): ?float
    {
        $distanceKm = $intervals->sum('distance_km');
        $energyKwh = $intervals->sum('energy_kwh');

        return $this->calculateKwhPer100Km($distanceKm > 0 ? $distanceKm : null, $energyKwh > 0 ? $energyKwh : null);
    }

    public function getVehiclesForUserWithFuelStats(int $userId): Collection
    {
        return Vehicle::query()
            ->where('user_id', $userId)
            ->whereHas('fuelLogs')
            ->withSum('fuelLogs', 'distance_km')
            ->withSum('fuelLogs', 'fuel_liters')
            ->withSum('fuelLogs', 'energy_kwh')
            ->with(['fuelLogs' => fn ($query) => $query
                ->select('id', 'vehicle_id', 'entry_type', 'fuel_date', 'odometer_km', 'distance_km', 'fuel_liters', 'energy_kwh', 'price_per_liter', 'price_per_kwh', 'total_cost')
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
                (string) $vehicle->id => $vehicle->nickname ?: ($vehicle->brand.' '.$vehicle->model),
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
