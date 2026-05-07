<?php

namespace App\Filament\Widgets;

use App\Services\VehicleCostService;
use Filament\Widgets\ChartWidget;

class CostBreakdownByVehicleChart extends ChartWidget
{
    protected ?string $heading = 'Kostenverdeling per voertuig';

    protected ?string $description = 'Vergelijk aanschaf, onderhoud en brandstof per voertuig.';

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $points = app(VehicleCostService::class)->getCostBreakdownByVehicleForUser(auth()->id());

        return [
            'datasets' => [
                [
                    'label' => 'Aanschaf',
                    'data' => $points['purchase'],
                    'backgroundColor' => '#111827',
                    'borderWidth' => 0,
                ],
                [
                    'label' => 'Onderhoud',
                    'data' => $points['maintenance'],
                    'backgroundColor' => '#ffd200',
                    'borderWidth' => 0,
                ],
                [
                    'label' => 'Brandstof',
                    'data' => $points['fuel'],
                    'backgroundColor' => '#f97316',
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $points['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
