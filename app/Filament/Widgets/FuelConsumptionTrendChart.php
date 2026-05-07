<?php

namespace App\Filament\Widgets;

use App\Services\FuelConsumptionService;
use Filament\Widgets\ChartWidget;

class FuelConsumptionTrendChart extends ChartWidget
{
    protected ?string $heading = 'Verbruikstrend';

    protected ?string $description = 'De laatste 8 tankbeurten van het geselecteerde voertuig.';

    protected int | string | array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public ?string $filter = null;

    public string $consumptionUnit = FuelConsumptionService::UNIT_L_PER_100_KM;

    public function mount(): void
    {
        $service = app(FuelConsumptionService::class);

        $this->consumptionUnit = $service->normalizeUnit(auth()->user()?->consumption_unit);
        $this->filter = (string) ($service->resolveDefaultTrendVehicleId(auth()->id()) ?? '');

        parent::mount();
    }

    public function updatedFilter(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }

    protected function getData(): array
    {
        $service = app(FuelConsumptionService::class);
        $vehicleId = filled($this->filter) ? (int) $this->filter : null;
        $points = $service->getRecentConsumptionTrendForUser(auth()->id(), $this->consumptionUnit, $vehicleId);

        return [
            'datasets' => [
                [
                    'label' => 'Verbruik',
                    'data' => $points['averages'],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.14)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $points['labels'],
        ];
    }

    protected function getFilters(): ?array
    {
        return app(FuelConsumptionService::class)
            ->getTrendVehicleOptionsForUser(auth()->id());
    }

    protected function getType(): string
    {
        return 'line';
    }
}
