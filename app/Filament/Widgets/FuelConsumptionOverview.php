<?php

namespace App\Filament\Widgets;

use App\Services\FuelConsumptionService;
use Filament\Widgets\Widget;

class FuelConsumptionOverview extends Widget
{
    protected string $view = 'filament.widgets.fuel-consumption-overview';

    protected int | string | array $columnSpan = 1;

    public string $consumptionUnit = FuelConsumptionService::UNIT_L_PER_100_KM;

    public function mount(): void
    {
        $this->consumptionUnit = app(FuelConsumptionService::class)->normalizeUnit(auth()->user()?->consumption_unit);
    }

    public function setConsumptionUnit(string $unit): void
    {
        $service = app(FuelConsumptionService::class);

        $this->consumptionUnit = $service->normalizeUnit($unit);
        auth()->user()?->forceFill([
            'consumption_unit' => $this->consumptionUnit,
        ])->save();
    }

    public function getViewData(): array
    {
        $service = app(FuelConsumptionService::class);
        $vehicles = $service->getVehiclesForUserWithFuelStats(auth()->id())
            ->map(function ($vehicle) use ($service) {
                $vehicle->fuel_average_label = $service->formatAverage(
                    (float) $vehicle->fuel_logs_sum_distance_km,
                    (float) $vehicle->fuel_logs_sum_fuel_liters,
                    $this->consumptionUnit
                );

                return $vehicle;
            });

        return [
            'vehicles' => $vehicles,
            'consumptionUnit' => $this->consumptionUnit,
            'supportedUnits' => $service->getSupportedUnits(),
        ];
    }
}
