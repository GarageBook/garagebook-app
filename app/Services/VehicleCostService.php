<?php

namespace App\Services;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class VehicleCostService
{
    public function getVehiclesWithDashboardCostsForUser(int $userId): Collection
    {
        return Vehicle::query()
            ->where('user_id', $userId)
            ->withSum('maintenanceLogs as maintenance_costs_total', 'cost')
            ->with(['fuelLogs' => fn ($query) => $query
                ->select('id', 'vehicle_id', 'fuel_date', 'fuel_liters', 'price_per_liter')
                ->whereNotNull('price_per_liter')
                ->orderBy('fuel_date')])
            ->latest()
            ->get()
            ->map(fn (Vehicle $vehicle) => $this->decorateVehicle($vehicle));
    }

    public function getDashboardSummaryForUser(int $userId): array
    {
        $vehicles = $this->getVehiclesWithDashboardCostsForUser($userId);

        return [
            'vehicles' => $vehicles,
            'overall_total_cost' => $vehicles->sum('dashboard_total_cost'),
            'overall_monthly_cost' => $vehicles->sum('dashboard_monthly_cost'),
        ];
    }

    public function getCostBreakdownByVehicleForUser(int $userId): array
    {
        $vehicles = $this->getVehiclesWithDashboardCostsForUser($userId);

        return [
            'labels' => $vehicles
                ->map(fn (Vehicle $vehicle) => $vehicle->nickname ?: ($vehicle->brand . ' ' . $vehicle->model))
                ->all(),
            'purchase' => $vehicles
                ->map(fn (Vehicle $vehicle) => round((float) ($vehicle->purchase_price ?? 0), 2))
                ->all(),
            'maintenance' => $vehicles
                ->map(fn (Vehicle $vehicle) => round((float) ($vehicle->maintenance_costs_total ?? 0), 2))
                ->all(),
            'fuel' => $vehicles
                ->map(fn (Vehicle $vehicle) => round($vehicle->fuelLogs->sum(
                    fn ($fuelLog) => (float) $fuelLog->fuel_liters * (float) $fuelLog->price_per_liter
                ), 2))
                ->all(),
        ];
    }

    public function getMaintenanceActivityForUser(int $userId, int $months = 6): array
    {
        $months = max(1, $months);
        $vehicleIds = Vehicle::query()
            ->where('user_id', $userId)
            ->pluck('id');

        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $monthBuckets = collect(range(0, $months - 1))
            ->map(fn (int $offset) => $start->addMonths($offset));

        $counts = MaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereDate('maintenance_date', '>=', $start->toDateString())
            ->get(['maintenance_date'])
            ->groupBy(fn (MaintenanceLog $log) => CarbonImmutable::parse($log->maintenance_date)->format('Y-m'))
            ->map(fn (Collection $logs) => $logs->count());

        return [
            'labels' => $monthBuckets
                ->map(fn (CarbonImmutable $month) => $month->translatedFormat('M Y'))
                ->all(),
            'counts' => $monthBuckets
                ->map(fn (CarbonImmutable $month) => $counts->get($month->format('Y-m'), 0))
                ->all(),
        ];
    }

    public function getCumulativeCostTrendForUser(int $userId, int $months = 12): array
    {
        $months = max(1, $months);
        $vehicleIds = Vehicle::query()
            ->where('user_id', $userId)
            ->pluck('id');

        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $monthBuckets = collect(range(0, $months - 1))
            ->map(fn (int $offset) => $start->addMonths($offset));

        $purchaseTotals = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->whereNotNull('purchase_price')
            ->whereDate('created_at', '>=', $start->toDateString())
            ->get(['created_at', 'purchase_price'])
            ->groupBy(fn (Vehicle $vehicle) => CarbonImmutable::parse($vehicle->created_at)->format('Y-m'))
            ->map(fn (Collection $vehicles) => round($vehicles->sum(
                fn (Vehicle $vehicle) => (float) ($vehicle->purchase_price ?? 0)
            ), 2));

        $maintenanceTotals = MaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereDate('maintenance_date', '>=', $start->toDateString())
            ->get(['maintenance_date', 'cost'])
            ->groupBy(fn (MaintenanceLog $log) => CarbonImmutable::parse($log->maintenance_date)->format('Y-m'))
            ->map(fn (Collection $logs) => round($logs->sum(
                fn (MaintenanceLog $log) => (float) ($log->cost ?? 0)
            ), 2));

        $fuelTotals = FuelLog::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('price_per_liter')
            ->whereDate('fuel_date', '>=', $start->toDateString())
            ->get(['fuel_date', 'fuel_liters', 'price_per_liter'])
            ->groupBy(fn (FuelLog $log) => CarbonImmutable::parse($log->fuel_date)->format('Y-m'))
            ->map(fn (Collection $logs) => round($logs->sum(
                fn (FuelLog $log) => (float) $log->fuel_liters * (float) $log->price_per_liter
            ), 2));

        $monthlyTotals = $monthBuckets->map(
            fn (CarbonImmutable $month) => round(
                (float) $purchaseTotals->get($month->format('Y-m'), 0.0)
                + (float) $maintenanceTotals->get($month->format('Y-m'), 0.0)
                + (float) $fuelTotals->get($month->format('Y-m'), 0.0),
                2
            )
        )->values();

        $runningTotal = 0.0;

        return [
            'labels' => $monthBuckets
                ->map(fn (CarbonImmutable $month) => $month->translatedFormat('M Y'))
                ->all(),
            'monthly_totals' => $monthlyTotals->all(),
            'cumulative_totals' => $monthlyTotals
                ->map(function (float $total) use (&$runningTotal) {
                    $runningTotal = round($runningTotal + $total, 2);

                    return $runningTotal;
                })
                ->all(),
        ];
    }

    protected function decorateVehicle(Vehicle $vehicle): Vehicle
    {
        $purchasePrice = (float) ($vehicle->purchase_price ?? 0);
        $maintenanceTotal = (float) ($vehicle->maintenance_costs_total ?? 0);
        $fuelTotal = $vehicle->fuelLogs->sum(
            fn ($fuelLog) => (float) $fuelLog->fuel_liters * (float) $fuelLog->price_per_liter
        );

        $vehicle->dashboard_total_cost = round($purchasePrice + $maintenanceTotal + $fuelTotal, 2);
        $vehicle->dashboard_monthly_cost = round(
            (float) ($vehicle->insurance_cost_per_month ?? 0)
            + (float) ($vehicle->road_tax_cost_per_month ?? 0)
            + $this->averageFuelCostPerMonth($vehicle->fuelLogs),
            2
        );

        return $vehicle;
    }

    protected function averageFuelCostPerMonth(Collection $fuelLogs): float
    {
        if ($fuelLogs->isEmpty()) {
            return 0.0;
        }

        $firstDate = Carbon::parse($fuelLogs->first()->fuel_date)->startOfMonth();
        $lastDate = Carbon::parse($fuelLogs->last()->fuel_date)->startOfMonth();
        $monthSpan = max(1, $firstDate->diffInMonths($lastDate) + 1);
        $totalFuelCost = $fuelLogs->sum(
            fn ($fuelLog) => (float) $fuelLog->fuel_liters * (float) $fuelLog->price_per_liter
        );

        return round($totalFuelCost / $monthSpan, 2);
    }
}
