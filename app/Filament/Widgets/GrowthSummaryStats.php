<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsDailySummary;
use App\Models\SearchConsoleDailySummary;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class GrowthSummaryStats extends Widget
{
    protected string $view = 'filament.widgets.growth-summary-stats';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() ?? false)
            && Schema::hasTable('analytics_daily_summaries')
            && Schema::hasTable('search_console_daily_summaries');
    }

    protected function getViewData(): array
    {
        $fromDate = Carbon::today()->subDays(29)->toDateString();

        $ga4 = AnalyticsDailySummary::query()
            ->whereDate('date', '>=', $fromDate)
            ->selectRaw('SUM(users) as users')
            ->selectRaw('SUM(screen_page_views) as screen_page_views')
            ->first();

        $searchConsole = SearchConsoleDailySummary::query()
            ->whereDate('date', '>=', $fromDate)
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('AVG(position) as average_position')
            ->first();

        $hasGa4Data = AnalyticsDailySummary::query()->exists();
        $hasSearchConsoleData = SearchConsoleDailySummary::query()->exists();
        $hasAnyData = $hasGa4Data || $hasSearchConsoleData;
        $clicks = (int) ($searchConsole?->clicks ?? 0);
        $impressions = (int) ($searchConsole?->impressions ?? 0);

        return [
            'emptyStateMessage' => 'Nog geen analyticsdata beschikbaar. Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.',
            'hasAnyData' => $hasAnyData,
            'stats' => [
                [
                    'label' => 'GA4 users laatste 30 dagen',
                    'value' => number_format((int) ($ga4?->users ?? 0), 0, ',', '.'),
                ],
                [
                    'label' => 'GA4 pageviews laatste 30 dagen',
                    'value' => number_format((int) ($ga4?->screen_page_views ?? 0), 0, ',', '.'),
                ],
                [
                    'label' => 'Search Console clicks laatste 30 dagen',
                    'value' => number_format($clicks, 0, ',', '.'),
                ],
                [
                    'label' => 'Search Console impressions laatste 30 dagen',
                    'value' => number_format($impressions, 0, ',', '.'),
                ],
                [
                    'label' => 'Gemiddelde CTR',
                    'value' => $impressions > 0
                        ? number_format(($clicks / $impressions) * 100, 2, ',', '.') . '%'
                        : '—',
                ],
                [
                    'label' => 'Gemiddelde positie',
                    'value' => $searchConsole?->average_position !== null
                        ? number_format((float) $searchConsole->average_position, 2, ',', '.')
                        : '—',
                ],
            ],
        ];
    }
}
