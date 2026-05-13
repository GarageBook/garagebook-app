<?php

namespace App\Filament\Widgets;

use App\Services\VehicleCostService;
use Filament\Widgets\ChartWidget;

class MonthlyCostTrendChart extends ChartWidget
{
    protected ?string $heading = 'Kosten per maand';

    protected ?string $description = 'Onderhoud en brandstofkosten over de afgelopen 6 maanden.';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $points = app(VehicleCostService::class)->getMonthlyCostTrendForUser(auth()->id());

        return [
            'datasets' => [
                [
                    'label' => 'Totaal',
                    'data' => $points['totals'],
                    'borderColor' => '#111827',
                    'backgroundColor' => 'rgba(17, 24, 39, 0.08)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Onderhoud',
                    'data' => $points['maintenance'],
                    'borderColor' => '#ffd200',
                    'backgroundColor' => 'rgba(255, 210, 0, 0.16)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Brandstof',
                    'data' => $points['fuel'],
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.14)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $points['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
