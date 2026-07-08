<?php

namespace App\Filament\Pages;

use App\Services\Gsc\SearchConsoleInsightsService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SearchConsoleInsights extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 193;

    protected string $view = 'filament.pages.search-console-insights';

    public array $dashboard = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.search-console-insights.export')),
        ];
    }

    public function mount(SearchConsoleInsightsService $service): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->dashboard = $service->dashboard();
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
        return 'SEO';
    }

    public static function getNavigationLabel(): string
    {
        return 'Search Console Insights';
    }

    public function getHeading(): string
    {
        return 'Search Console Insights';
    }

    public function getTitle(): string
    {
        return 'Search Console Insights';
    }

    public function getSubheading(): ?string
    {
        return 'SEO-prioriteiten op basis van geimporteerde Search Console CSV-data.';
    }
}
