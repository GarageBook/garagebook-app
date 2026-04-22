<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class UserGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Gebruikersgroei';

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Nieuwe gebruikers per maand in de afgelopen 12 maanden.';
    }

    protected function getData(): array
    {
        $points = self::buildMonthlyGrowthData(User::query()->pluck('created_at'));

        return [
            'datasets' => [
                [
                    'label' => 'Nieuwe gebruikers',
                    'data' => $points['counts'],
                    'borderColor' => '#ffd200',
                    'backgroundColor' => 'rgba(255, 210, 0, 0.18)',
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

    public static function buildMonthlyGrowthData(Collection $timestamps): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(
            fn (int $offset) => $start->addMonths($offset)
        );

        $countsByMonth = $timestamps
            ->filter()
            ->map(fn ($timestamp) => CarbonImmutable::parse($timestamp)->format('Y-m'))
            ->countBy();

        return [
            'labels' => $months
                ->map(fn (CarbonImmutable $month) => $month->translatedFormat('M Y'))
                ->all(),
            'counts' => $months
                ->map(fn (CarbonImmutable $month) => $countsByMonth->get($month->format('Y-m'), 0))
                ->all(),
        ];
    }
}
