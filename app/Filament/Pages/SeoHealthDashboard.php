<?php

namespace App\Filament\Pages;

use App\Services\Seo\SeoHealthService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SeoHealthDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static ?int $navigationSort = 192;

    protected static ?string $slug = 'seo-health-dashboard';

    protected string $view = 'filament.pages.seo-health-dashboard';

    public array $report = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.seo-health-dashboard.export')),
        ];
    }

    public function mount(SeoHealthService $seoHealthService): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->report = $seoHealthService->report();
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

    public static function getNavigationUrl(): string
    {
        return static::getUrl(panel: 'admin');
    }

    public static function getNavigationLabel(): string
    {
        return 'SEO Health';
    }

    public function getHeading(): string
    {
        return 'SEO Health';
    }

    public function getTitle(): string
    {
        return 'SEO Health';
    }

    public function getSubheading(): ?string
    {
        return 'Read-only controle van publieke garagepagina\'s, sitemap, canonical en structured data.';
    }
}
