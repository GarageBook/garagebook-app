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
                    ->label('Voertuig')
                    ->relationship('vehicle', 'model')
                    ->required(),

                Forms\Components\TextInput::make('description')
                    ->label('Omschrijving')
                    ->required(),

                Forms\Components\TextInput::make('km_reading')
                    ->label('Kilometerstand')
                    ->numeric()
                    ->suffix(' km')
                    ->required(),

                Forms\Components\DatePicker::make('maintenance_date')
                    ->label('Onderhoudsdatum')
                    ->required(),

                Forms\Components\TextInput::make('cost')
                    ->label('Kosten')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\FileUpload::make('attachments')
                    ->label('Foto’s')
                    ->disk('public')
                    ->directory('maintenance-attachments')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    ->image()
                    ->imageEditor()
                    ->multiple()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->previewable(true)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label('Notities')
                    ->columnSpanFull(),
            ]);
    }
}