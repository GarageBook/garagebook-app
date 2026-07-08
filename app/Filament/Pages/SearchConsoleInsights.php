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

    public array $opportunityFilters = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.search-console-insights.export')),
            Action::make('exportOpportunitiesCsv')
                ->label('SEO Opportunities CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.seo-opportunities.export', $this->opportunityFilters)),
        ];
    }

    public function mount(SearchConsoleInsightsService $service): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->opportunityFilters = collect(request()->only(['type', 'page_type', 'min_score', 'brand', 'date']))
            ->filter(fn ($value): bool => filled($value))
            ->all();
        $this->dashboard = $service->dashboard($this->opportunityFilters);
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
