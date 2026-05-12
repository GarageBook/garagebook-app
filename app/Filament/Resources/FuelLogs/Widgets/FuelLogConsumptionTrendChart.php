<?php

namespace App\Filament\Resources\FuelLogs\Widgets;

use App\Services\DistanceUnitService;
use App\Services\FuelConsumptionService;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class FuelLogConsumptionTrendChart extends ChartWidget
{
    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected static bool $isLazy = false;

    public ?int $vehicleId = null;

    public ?string $distanceUnit = null;

    protected function getData(): array
    {
        if (! $this->vehicleId || ! auth()->id()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $points = app(FuelConsumptionService::class)->getConsumptionTrendForVehicle(auth()->id(), $this->vehicleId);

        if (! $points['has_enough_points']) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $datasets = [[
            'label' => 'L/100 km',
            'data' => $points['liters_per_100_km'],
            'borderColor' => '#2f7d32',
            'backgroundColor' => 'rgba(47, 125, 50, 0.10)',
            'pointBackgroundColor' => '#2f7d32',
            'pointBorderColor' => '#ffffff',
            'pointRadius' => 3,
            'fill' => true,
            'tension' => 0.35,
            'yAxisID' => 'y',
        ]];

        if ($this->usesMiles()) {
            $datasets[] = [
                'label' => 'MPG (US)',
                'data' => $points['mpg_us'],
                'borderColor' => '#94a3b8',
                'backgroundColor' => 'rgba(148, 163, 184, 0.06)',
                'pointBackgroundColor' => '#94a3b8',
                'pointBorderColor' => '#ffffff',
                'pointRadius' => 2,
                'borderDash' => [6, 6],
                'fill' => false,
                'tension' => 0.35,
                'yAxisID' => 'y1',
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $points['labels'],
        ];
    }

    public function getHeading(): string
    {
        return __('fuel.chart.heading');
    }

    public function getDescription(): ?string
    {
        if (! $this->vehicleId) {
            return __('fuel.chart.empty');
        }

        $points = app(FuelConsumptionService::class)->getConsumptionTrendForVehicle(auth()->id(), $this->vehicleId);

        if (! $points['has_enough_points']) {
            return __('fuel.chart.empty');
        }

        if ($this->usesMiles()) {
            return __('fuel.chart.description_miles');
        }

        return __('fuel.chart.description_metric');
    }

    protected function getOptions(): array | RawJs | null
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => $this->usesMiles(),
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 10,
                        'color' => '#475569',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'color' => '#64748b',
                    ],
                ],
                'y' => [
                    'beginAtZero' => false,
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, 0.18)',
                    ],
                    'ticks' => [
                        'color' => '#64748b',
                    ],
                ],
                'y1' => [
                    'display' => $this->usesMiles(),
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'color' => '#94a3b8',
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function usesMiles(): bool
    {
        return app(DistanceUnitService::class)->normalizeUnit($this->distanceUnit) === DistanceUnitService::UNIT_MILES;
    }
}
