<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsTopPage;
use App\Support\AnalyticsDataWindow;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TopVisitedPagesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() ?? false)
            && Schema::hasTable('analytics_top_pages');
    }

    protected function getTableHeading(): string
    {
        return 'Top Visited Pages';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultKeySort(false)
            ->defaultPaginationPageOption(10)
            ->paginated(false)
            ->description($this->tableDescription())
            ->emptyStateHeading('Nog geen gesynchroniseerde analyticsdata beschikbaar.')
            ->emptyStateDescription('Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.')
            ->columns([
                TextColumn::make('page_path')
                    ->label('Path')
                    ->wrap(),
                TextColumn::make('page_title')
                    ->label('Titel')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('views')
                    ->label('Views')
                    ->numeric(),
                TextColumn::make('users')
                    ->label('Users')
                    ->numeric()
                    ->placeholder('—'),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $window = AnalyticsDataWindow::forTable('analytics_top_pages');

        return AnalyticsTopPage::query()
            ->when($window['has_data'], fn ($query) => $query
                ->where('date', '>=', $window['start_at'])
                ->where('date', '<=', $window['end_at']))
            ->when(! $window['has_data'], fn ($query) => $query->whereRaw('1 = 0'))
            ->selectRaw('MIN(id) as id')
            ->selectRaw('page_path')
            ->selectRaw('MAX(page_title) as page_title')
            ->selectRaw('SUM(views) as views')
            ->selectRaw('SUM(users) as users')
            ->groupBy('page_path')
            ->orderByDesc('views')
            ->limit(10);
    }

    private function tableDescription(): ?string
    {
        $window = AnalyticsDataWindow::forTable('analytics_top_pages');

        if (! $window['has_data']) {
            return null;
        }

        return collect([$window['label'], $window['warning']])
            ->filter()
            ->implode(' · ');
    }
}
