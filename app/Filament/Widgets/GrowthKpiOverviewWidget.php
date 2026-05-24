<?php

namespace App\Filament\Widgets;

use App\Support\Growth\GrowthDashboardData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GrowthKpiOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'KPI-overzicht';

    protected ?string $description = 'Bezoekers- en registratiecijfers uit lokaal opgeslagen analytics- en gebruikersdata.';

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 4,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        $data = app(GrowthDashboardData::class)->kpiOverview();

        return [
            'is_analytics_incomplete' => $data['is_analytics_incomplete'] ?? false,
        ];
    }

    protected function getStats(): array
    {
        $cards = app(GrowthDashboardData::class)->kpiOverview()['cards'];

        return collect($cards)
            ->map(function (array $card, int $index): Stat {
                $value = $card['is_available']
                    ? $this->formatValue($card['value'], $card['suffix'] ?? null)
                    : 'niet beschikbaar';

                $stat = Stat::make($card['label'], $value);

                if (! empty($card['meta'])) {
                    $stat->description($card['meta']);
                }

                return $stat->color(match ($index) {
                    0, 1, 2 => 'info',
                    3, 4, 5 => 'success',
                    6 => 'warning',
                    default => 'gray',
                });
            })
            ->all();
    }

    private function formatValue(mixed $value, ?string $suffix = null): string
    {
        if (is_numeric($value)) {
            $decimals = $suffix === '%' ? 1 : 0;
            $value = number_format((float) $value, $decimals, ',', '.');
        }

        if ($suffix !== null) {
            $value .= $suffix;
        }

        return (string) $value;
    }
}
