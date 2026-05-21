<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GrowthDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = 191;

    protected string $view = 'filament.pages.growth-dashboard';

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
        return 'Experimenteel beheer-dashboard met acquisition-, SEO-, funnel- en activatiestatistieken op basis van lokaal opgeslagen data.';
    }
}
