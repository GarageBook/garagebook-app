<?php

namespace App\Filament\Widgets;

use App\Services\VehicleCostService;
use Filament\Widgets\Widget;

class MaintenanceCosts extends Widget
{
    protected string $view = 'filament.widgets.maintenance-costs';

    protected int | string | array $columnSpan = 1;

    public function getViewData(): array
    {
        $summary = app(VehicleCostService::class)->getDashboardSummaryForUser(auth()->id());

        return [
            'overallTotalCost' => $summary['overall_total_cost'],
            'overallMonthlyCost' => $summary['overall_monthly_cost'],
            'hasVehicles' => $summary['vehicles']->isNotEmpty(),
        ];
    }
}
