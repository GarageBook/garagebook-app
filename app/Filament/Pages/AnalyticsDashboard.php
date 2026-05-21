<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AnalyticsDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 190;

    protected string $view = 'filament.pages.analytics-dashboard';

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
