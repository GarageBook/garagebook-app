<?php

namespace App\Filament\Resources\MaintenanceLogs;

use App\Filament\Resources\MaintenanceLogs\Pages\CreateMaintenanceLog;
use App\Filament\Resources\MaintenanceLogs\Pages\EditMaintenanceLog;
use App\Filament\Resources\MaintenanceLogs\Pages\ListMaintenanceLogs;
use App\Filament\Resources\MaintenanceLogs\Schemas\MaintenanceLogForm;
use App\Filament\Resources\MaintenanceLogs\Tables\MaintenanceLogsTable;
use App\Models\MaintenanceLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceLogResource extends Resource
{
    protected static ?string $model = MaintenanceLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('vehicle', function ($query) {
                $query->where('user_id', auth()->id());
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceLogs::route('/'),
            'create' => CreateMaintenanceLog::route('/create'),
            'edit' => EditMaintenanceLog::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('maintenance.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('maintenance.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('maintenance.plural_model_label');
    }
}
