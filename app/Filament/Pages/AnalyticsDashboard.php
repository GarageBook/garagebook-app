<?php

namespace App\Filament\Pages;

use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsTopPage;
use App\Models\SearchConsoleDailySummary;
use App\Models\SearchConsolePage;
use App\Models\SearchConsoleQuery;
use App\Support\AnalyticsEventTracker;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class AnalyticsDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 190;

    protected string $view = 'filament.pages.analytics-dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    app(AnalyticsEventTracker::class)->queueExportClicked('analytics');

                    return response()->streamDownload(function () {
                        echo "\xEF\xBB\xBF"; // UTF-8 BOM
                        $handle = fopen('php://output', 'w');

                        fputcsv($handle, ['section', 'key', 'value', 'date']);

                        $fromDate = Carbon::today()->subDays(29);
                        $fromDateStr = $fromDate->toDateString();

                        // 1. Analytics Overview
                        $ga4 = AnalyticsDailySummary::query()
                            ->whereDate('date', '>=', $fromDateStr)
                            ->selectRaw('SUM(users) as users')
                            ->selectRaw('SUM(screen_page_views) as screen_page_views')
                            ->first();

                        $searchConsole = SearchConsoleDailySummary::query()
                            ->whereDate('date', '>=', $fromDateStr)
                            ->selectRaw('SUM(clicks) as clicks')
                            ->selectRaw('SUM(impressions) as impressions')
                            ->selectRaw('AVG(position) as average_position')
                            ->first();

                        $clicks = (int) ($searchConsole?->clicks ?? 0);
                        $impressions = (int) ($searchConsole?->impressions ?? 0);

                        fputcsv($handle, ['analytics_overview', 'ga4_users', $ga4?->users ?? 0, 'last_30_days']);
                        fputcsv($handle, ['analytics_overview', 'ga4_pageviews', $ga4?->screen_page_views ?? 0, 'last_30_days']);
                        fputcsv($handle, ['analytics_overview', 'search_console_clicks', $clicks, 'last_30_days']);
                        fputcsv($handle, ['analytics_overview', 'search_console_impressions', $impressions, 'last_30_days']);
                        fputcsv($handle, ['analytics_overview', 'average_ctr', $impressions > 0 ? round(($clicks / $impressions) * 100, 2) . '%' : '—', 'last_30_days']);
                        fputcsv($handle, ['analytics_overview', 'average_position', $searchConsole?->average_position !== null ? round((float) $searchConsole->average_position, 2) : '—', 'last_30_days']);

                        // 2. Top Search Queries
                        $queries = SearchConsoleQuery::query()
                            ->whereDate('date', '>=', $fromDateStr)
                            ->selectRaw('query, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
                            ->groupBy('query')
                            ->orderByDesc('clicks')
                            ->limit(50)
                            ->get();

                        foreach ($queries as $query) {
                            fputcsv($handle, ['top_search_queries', $query->query, "Clicks: {$query->clicks}, Impressions: {$query->impressions}, CTR: " . round($query->ctr * 100, 2) . "%, Pos: " . round($query->position, 2), 'last_30_days']);
                        }

                        // 3. Top SEO Pages
                        $seoPages = SearchConsolePage::query()
                            ->whereDate('date', '>=', $fromDateStr)
                            ->selectRaw('page, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
                            ->groupBy('page')
                            ->orderByDesc('clicks')
                            ->limit(50)
                            ->get();

                        foreach ($seoPages as $page) {
                            fputcsv($handle, ['top_seo_pages', $page->page, "Clicks: {$page->clicks}, Impressions: {$page->impressions}, CTR: " . round($page->ctr * 100, 2) . "%, Pos: " . round($page->position, 2), 'last_30_days']);
                        }

                        // 4. Top Visited Pages
                        $visitedPages = AnalyticsTopPage::query()
                            ->whereDate('date', '>=', $fromDateStr)
                            ->selectRaw('page_path, SUM(views) as views, SUM(users) as users')
                            ->groupBy('page_path')
                            ->orderByDesc('views')
                            ->limit(50)
                            ->get();

                        foreach ($visitedPages as $page) {
                            fputcsv($handle, ['top_visited_pages', $page->page_path, "Views: {$page->views}, Users: {$page->users}", 'last_30_days']);
                        }

                        fclose($handle);
                    }, 'analytics-export-' . now()->format('Y-m-d') . '.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.navigation.management');
    }

    public static function getNavigationLabel(): string
    {
        return 'Analytics dashboard';
    }

    public function getHeading(): string
    {
        return 'Analytics dashboard';
    }

    public function getTitle(): string
    {
        return 'Analytics dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'GA4- en Search Console-data voor beheer en SEO-monitoring.';
    }
}
