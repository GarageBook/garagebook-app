<?php

namespace App\Filament\Widgets;

use App\Services\VehicleCostService;
use Filament\Widgets\ChartWidget;

class MaintenanceActivityChart extends ChartWidget
{
    protected ?string $heading = 'Onderhoudsmomenten per maand';

    protected ?string $description = 'Aantal onderhoudsacties over de afgelopen 6 maanden.';

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $points = app(VehicleCostService::class)->getMaintenanceActivityForUser(auth()->id());

        return [
            'datasets' => [
                [
                    'label' => 'Onderhoudsmomenten',
                    'data' => $points['counts'],
                    'backgroundColor' => '#2563eb',
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
