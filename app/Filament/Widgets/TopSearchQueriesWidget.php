<?php

namespace App\Filament\Widgets;

use App\Models\SearchConsoleQuery;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TopSearchQueriesWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() ?? false)
            && Schema::hasTable('search_console_queries');
    }

    protected function getTableHeading(): string
    {
        return 'Top Search Queries';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultKeySort(false)
            ->defaultPaginationPageOption(10)
            ->paginated(false)
            ->emptyStateHeading('Nog geen analyticsdata beschikbaar.')
            ->emptyStateDescription('Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.')
            ->columns([
                TextColumn::make('query')
                    ->label('Query')
                    ->wrap(),
                TextColumn::make('clicks')
                    ->label('Clicks')
                    ->numeric(),
                TextColumn::make('impressions')
                    ->label('Impressions')
                    ->numeric(),
                TextColumn::make('ctr')
                    ->label('CTR')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state * 100, 2, ',', '.') . '%' : '—'),
                TextColumn::make('position')
                    ->label('Positie')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2, ',', '.') : '—'),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $fromDate = Carbon::today()->subDays(29)->toDateString();

        return SearchConsoleQuery::query()
            ->whereDate('date', '>=', $fromDate)
            ->selectRaw('MIN(id) as id')
            ->selectRaw('query')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('AVG(ctr) as ctr')
            ->selectRaw('AVG(position) as position')
            ->groupBy('query')
            ->orderByDesc('clicks')
            ->limit(10);
    }
}
