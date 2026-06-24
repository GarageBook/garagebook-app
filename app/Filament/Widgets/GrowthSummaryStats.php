<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsDailySummary;
use App\Models\MaintenanceLog;
use App\Models\SearchConsoleDailySummary;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsDataWindow;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class GrowthSummaryStats extends Widget
{
    protected string $view = 'filament.widgets.growth-summary-stats';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() ?? false)
            && Schema::hasTable('analytics_daily_summaries')
            && Schema::hasTable('search_console_daily_summaries');
    }

    protected function getViewData(): array
    {
        $ga4Window = AnalyticsDataWindow::forTable('analytics_daily_summaries');
        $searchConsoleWindow = AnalyticsDataWindow::forTable('search_console_daily_summaries');

        $ga4 = $ga4Window['has_data']
            ? AnalyticsDailySummary::query()
                ->where('date', '>=', $ga4Window['start_at'])
                ->where('date', '<=', $ga4Window['end_at'])
                ->selectRaw('SUM(users) as users')
                ->selectRaw('SUM(screen_page_views) as screen_page_views')
                ->first()
            : null;

        $searchConsole = $searchConsoleWindow['has_data']
            ? SearchConsoleDailySummary::query()
                ->where('date', '>=', $searchConsoleWindow['start_at'])
                ->where('date', '<=', $searchConsoleWindow['end_at'])
                ->selectRaw('SUM(clicks) as clicks')
                ->selectRaw('SUM(impressions) as impressions')
                ->selectRaw('AVG(position) as average_position')
                ->first()
            : null;

        $hasGa4Data = $ga4Window['has_data'];
        $hasSearchConsoleData = $searchConsoleWindow['has_data'];
        $hasAnyData = $hasGa4Data || $hasSearchConsoleData;
        $clicks = (int) ($searchConsole?->clicks ?? 0);
        $impressions = (int) ($searchConsole?->impressions ?? 0);

        return [
            'emptyStateMessage' => 'Nog geen analyticsdata beschikbaar. Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.',
            'ga4Window' => $ga4Window,
            'searchConsoleWindow' => $searchConsoleWindow,
            'syncWarnings' => collect([$ga4Window['warning'], $searchConsoleWindow['warning']])
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'hasAnyData' => $hasAnyData,
            'productStats' => $this->productStats(),
            'stats' => [
                [
                    'label' => 'GA4 users',
                    'value' => $hasGa4Data ? number_format((int) ($ga4?->users ?? 0), 0, ',', '.') : 'niet beschikbaar',
                    'meta' => $ga4Window['label'],
                ],
                [
                    'label' => 'GA4 pageviews',
                    'value' => $hasGa4Data ? number_format((int) ($ga4?->screen_page_views ?? 0), 0, ',', '.') : 'niet beschikbaar',
                    'meta' => $ga4Window['label'],
                ],
                [
                    'label' => 'Search Console clicks',
                    'value' => $hasSearchConsoleData ? number_format($clicks, 0, ',', '.') : 'niet beschikbaar',
                    'meta' => $searchConsoleWindow['label'],
                ],
                [
                    'label' => 'Search Console impressions',
                    'value' => $hasSearchConsoleData ? number_format($impressions, 0, ',', '.') : 'niet beschikbaar',
                    'meta' => $searchConsoleWindow['label'],
                ],
                [
                    'label' => 'Gemiddelde CTR',
                    'value' => $hasSearchConsoleData && $impressions > 0
                        ? number_format(($clicks / $impressions) * 100, 2, ',', '.').'%'
                        : '—',
                    'meta' => $searchConsoleWindow['label'],
                ],
                [
                    'label' => 'Gemiddelde positie',
                    'value' => $hasSearchConsoleData && $searchConsole?->average_position !== null
                        ? number_format((float) $searchConsole->average_position, 2, ',', '.')
                        : '—',
                    'meta' => $searchConsoleWindow['label'],
                ],
            ],
        ];
    }

    private function productStats(): array
    {
        return [
            [
                'label' => 'Users totaal',
                'value' => Schema::hasTable('users') ? User::query()->count() : null,
            ],
            [
                'label' => 'Voertuigen totaal',
                'value' => Schema::hasTable('vehicles') ? Vehicle::query()->count() : null,
            ],
            [
                'label' => 'Onderhoudslogs totaal',
                'value' => Schema::hasTable('maintenance_logs') ? MaintenanceLog::query()->count() : null,
            ],
            [
                'label' => 'Publieke voertuigen',
                'value' => Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'is_public')
                    ? Vehicle::query()->where('is_public', true)->count()
                    : null,
            ],
        ];
    }
}
