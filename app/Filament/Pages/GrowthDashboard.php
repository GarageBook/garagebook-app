<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GrowthAcquisitionPerformanceWidget;
use App\Filament\Widgets\GrowthKpiOverviewWidget;
use App\Filament\Widgets\GrowthLandingPageConversionWidget;
use App\Filament\Widgets\GrowthPartnerPerformanceWidget;
use App\Filament\Widgets\GrowthProductActivationFunnelWidget;
use App\Filament\Widgets\GrowthRecentActivityWidget;
use App\Filament\Widgets\GrowthSeoIntelligenceWidget;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class GrowthDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 191;

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
        return '';
    }

    public function getTitle(): string
    {
        return 'Growth dashboard';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.growth-dashboard-header');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GrowthKpiOverviewWidget::class,
            GrowthAcquisitionPerformanceWidget::class,
            GrowthPartnerPerformanceWidget::class,
            GrowthSeoIntelligenceWidget::class,
            GrowthLandingPageConversionWidget::class,
            GrowthProductActivationFunnelWidget::class,
            GrowthRecentActivityWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
