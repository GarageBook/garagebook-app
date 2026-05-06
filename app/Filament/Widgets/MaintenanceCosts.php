<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class MaintenanceCosts extends Widget
{
    protected string $view = 'filament.widgets.maintenance-costs';

    protected int | string | array $columnSpan = 1;

    public function getViewData(): array
    {
        $vehicles = auth()->user()
            ->vehicles()
            ->withSum('maintenanceLogs as maintenance_costs_total', 'cost')
            ->latest()
            ->get();

        $totalCost = $vehicles->sum(fn ($vehicle) => (float) ($vehicle->maintenance_costs_total ?? 0));

        return [
            'vehicles' => $vehicles,
            'totalCost' => $totalCost,
        ];
    }
}
