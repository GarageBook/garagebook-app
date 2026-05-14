<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsTopPage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TopVisitedPagesWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getTableHeading(): string
    {
        return 'Top Visited Pages';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultPaginationPageOption(10)
            ->paginated(false)
            ->emptyStateHeading('Nog geen analyticsdata beschikbaar.')
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
        $fromDate = Carbon::today()->subDays(29)->toDateString();

        return AnalyticsTopPage::query()
            ->whereDate('date', '>=', $fromDate)
            ->selectRaw('MIN(id) as id')
            ->selectRaw('page_path')
            ->selectRaw('MAX(page_title) as page_title')
            ->selectRaw('SUM(views) as views')
            ->selectRaw('SUM(users) as users')
            ->groupBy('page_path')
            ->orderByDesc('views')
            ->limit(10);
    }
}
