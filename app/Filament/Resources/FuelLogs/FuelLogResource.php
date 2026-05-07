<?php

namespace App\Filament\Resources\FuelLogs;

use App\Filament\Resources\FuelLogs\Pages\CreateFuelLog;
use App\Filament\Resources\FuelLogs\Pages\EditFuelLog;
use App\Filament\Resources\FuelLogs\Pages\ListFuelLogs;
use App\Filament\Resources\FuelLogs\Schemas\FuelLogForm;
use App\Filament\Resources\FuelLogs\Tables\FuelLogsTable;
use App\Models\FuelLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FuelLogResource extends Resource
{
    protected static ?string $model = FuelLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Verbruik';

    protected static ?string $modelLabel = 'Verbruiksregel';

    protected static ?string $pluralModelLabel = 'Verbruik';

    protected static ?string $slug = 'verbruik';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        return 'new!';
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'info';
    }

    public static function form(Schema $schema): Schema
    {
        return FuelLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FuelLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('vehicle', function (Builder $query): void {
                $query->where('user_id', auth()->id());
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFuelLogs::route('/'),
            'create' => CreateFuelLog::route('/create'),
            'edit' => EditFuelLog::route('/{record}/edit'),
        ];
    }
}
