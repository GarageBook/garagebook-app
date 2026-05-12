<?php

namespace App\Filament\Resources\TripLogs;

use App\Filament\Resources\TripLogs\Pages\CreateTripLog;
use App\Filament\Resources\TripLogs\Pages\EditTripLog;
use App\Filament\Resources\TripLogs\Pages\ListTripLogs;
use App\Filament\Resources\TripLogs\Pages\ViewTripLog;
use App\Filament\Resources\TripLogs\Schemas\TripLogForm;
use App\Filament\Resources\TripLogs\Schemas\TripLogInfolist;
use App\Filament\Resources\TripLogs\Tables\TripLogsTable;
use App\Models\TripLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripLogResource extends Resource
{
    protected static ?string $model = TripLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return TripLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TripLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TripLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('vehicle', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTripLogs::route('/'),
            'create' => CreateTripLog::route('/create'),
            'view' => ViewTripLog::route('/{record}'),
            'edit' => EditTripLog::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('trips.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('trips.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('trips.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return 'nieuw!';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }
}
