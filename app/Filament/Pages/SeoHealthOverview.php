<?php

namespace App\Filament\Pages;

use App\Services\Seo\SeoHealthService;
use Filament\Actions\Action;
use Filament\Pages\Page;

class SeoHealthOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'SEO Health';

    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 192;

    protected static ?string $slug = 'seo-health-dashboard';

    protected string $view = 'filament.pages.seo-health-overview';

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
