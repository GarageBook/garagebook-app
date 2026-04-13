<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\FileUpload::make('photo')
                    ->label('Voertuigfoto')
                    ->image()
                    ->disk('public')
                    ->directory('vehicle-photos')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ])
                    ->maxSize(5120)
                    ->downloadable()
                    ->openable()
                    ->imagePreviewHeight('200')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('brand')
                    ->label('Merk')
                    ->required(),

                Forms\Components\TextInput::make('model')
                    ->label('Model')
                    ->required(),

                Forms\Components\TextInput::make('nickname')
                    ->label('Titel'),

                Forms\Components\TextInput::make('license_plate')
                    ->label('Kenteken'),

                Forms\Components\TextInput::make('current_km')
                    ->label('Huidige kilometerstand')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('year')
                    ->label('Bouwjaar')
                    ->numeric(),

                Forms\Components\Textarea::make('notes')
                    ->label('Notities'),
            ]);
    }
}