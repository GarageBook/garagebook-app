<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GrowthAcquisitionPerformanceWidget;
use App\Filament\Widgets\GrowthKpiOverviewWidget;
use App\Filament\Widgets\GrowthLandingPageConversionWidget;
use App\Filament\Widgets\GrowthPartnerPerformanceWidget;
use App\Filament\Widgets\GrowthProductActivationFunnelWidget;
use App\Filament\Widgets\GrowthRecentActivityWidget;
use App\Filament\Widgets\GrowthSeoIntelligenceWidget;
use App\Filament\Widgets\LifecycleEmailStatsWidget;
use App\Filament\Widgets\LifecycleOverviewWidget;
use App\Support\AnalyticsEventTracker;
use App\Support\Growth\GrowthDashboardData;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GrowthDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 191;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export data')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    app(AnalyticsEventTracker::class)->queueExportClicked('growth');

                    return response()->streamDownload(function () {
                        echo "\xEF\xBB\xBF";
                        $handle = fopen('php://output', 'w');
                        fputcsv($handle, ['section', 'key', 'value', 'date']);

                        $data = app(GrowthDashboardData::class);

                        $kpis = $data->kpiOverview();
                        foreach ($kpis['cards'] as $card) {
                            fputcsv($handle, ['kpi_overview', $card['label'], $card['value'].($card['suffix'] ?? null), $card['meta'] ?? '']);
                        }

                        $acquisition = $data->acquisitionPerformance();
                        foreach ($acquisition['rows'] as $row) {
                            fputcsv($handle, ['acquisition_performance', "{$row['source']} / {$row['medium']} / {$row['campaign']}", "Registrations: {$row['registrations']}", $row['latest_activity']]);
                        }

                        $partners = $data->partnerPerformance();
                        foreach ($partners['rows'] as $row) {
                            fputcsv($handle, ['partner_performance', $row['partner'], "Registrations: {$row['registrations']}", $row['latest_registration']]);
                        }

                        $seo = $data->seoIntelligence();
                        foreach ($seo['top_queries_by_clicks'] as $row) {
                            fputcsv($handle, ['seo_intelligence_queries', $row['label'], "Clicks: {$row['clicks']}, Impressions: {$row['impressions']}, CTR: {$row['ctr']}%, Pos: {$row['position']}", '']);
                        }
                        foreach ($seo['top_pages'] as $row) {
                            fputcsv($handle, ['seo_intelligence_pages', $row['label'], "Clicks: {$row['clicks']}, Impressions: {$row['impressions']}, CTR: {$row['ctr']}%, Pos: {$row['position']}", '']);
                        }

                        $landing = $data->landingPageConversion();
                        foreach ($landing['rows'] as $row) {
                            fputcsv($handle, ['landing_page_conversion', $row['landing_page'], "Visits: {$row['visits']}, Registrations: {$row['registrations']}, Conv: {$row['conversion_rate']}%, Top Source: {$row['top_source']}", $row['latest_registration']]);
                        }

                        $funnel = $data->activationFunnel();
                        foreach ($funnel['funnel'] as $row) {
                            fputcsv($handle, ['activation_funnel', $row['step'], "Count: {$row['count']}, Percentage: {$row['percentage']}%", '']);
                        }

                        fclose($handle);
                    }, 'growth-export-'.now()->format('Y-m-d').'.csv', [
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
        return 'Growth dashboard';
    }

    public function getHeading(): string
    {
        return 'Growth dashboard';
    }

    public function getTitle(): string
    {
        return 'Growth dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Experimenteel beheer-dashboard met acquisitie-, SEO-, funnel- en activatiestatistieken op basis van lokaal opgeslagen data.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GrowthKpiOverviewWidget::class,
            LifecycleOverviewWidget::class,
            LifecycleEmailStatsWidget::class,
            GrowthAcquisitionPerformanceWidget::class,
            GrowthPartnerPerformanceWidget::class,
            GrowthSeoIntelligenceWidget::class,
            GrowthLandingPageConversionWidget::class,
            GrowthProductActivationFunnelWidget::class,
            GrowthRecentActivityWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
