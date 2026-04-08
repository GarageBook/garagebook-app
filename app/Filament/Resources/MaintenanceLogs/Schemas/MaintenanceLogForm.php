<?php

namespace App\Filament\Resources\MaintenanceLogs\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class MaintenanceLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('vehicle_id')
                    ->relationship('vehicle', 'model')
                    ->required(),

                Forms\Components\TextInput::make('description')
                    ->required(),

                Forms\Components\TextInput::make('km_reading')
                    ->numeric()
                    ->required(),

                Forms\Components\DatePicker::make('maintenance_date')
                    ->required(),

                Forms\Components\TextInput::make('cost')
                    ->numeric(),

                Forms\Components\FileUpload::make('attachment')
                    ->directory('maintenance-files'),

                Forms\Components\Textarea::make('notes'),
            ]);
    }
}
