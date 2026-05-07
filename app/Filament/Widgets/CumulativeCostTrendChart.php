<?php

namespace App\Filament\Widgets;

use App\Services\VehicleCostService;
use Filament\Widgets\ChartWidget;

class CumulativeCostTrendChart extends ChartWidget
{
    protected ?string $heading = 'Cumulatieve kosten in de tijd';

    protected ?string $description = 'Oplopende totale kosten over de afgelopen 12 maanden.';

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $points = app(VehicleCostService::class)->getCumulativeCostTrendForUser(auth()->id());

        return [
            'datasets' => [
                [
                    'label' => 'Cumulatief totaal',
                    'data' => $points['cumulative_totals'],
                    'borderColor' => '#111827',
                    'backgroundColor' => 'rgba(17, 24, 39, 0.10)',
                    'fill' => true,
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
