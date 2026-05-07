<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\Carbon;
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
