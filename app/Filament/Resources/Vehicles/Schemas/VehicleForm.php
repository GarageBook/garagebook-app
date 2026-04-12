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
                Forms\Components\TextInput::make('brand')
                    ->required(),

                Forms\Components\TextInput::make('model')
                    ->required(),

                Forms\Components\TextInput::make('nickname'),

                Forms\Components\TextInput::make('license_plate'),

                Forms\Components\TextInput::make('current_km')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('year')
                    ->numeric(),

                Forms\Components\Textarea::make('notes'),

                Forms\Components\FileUpload::make('photo')
                    ->label('Foto')
                    ->image()
                    ->disk('public')
                    ->directory('vehicles')
                    ->imageEditor()
                    ->columnSpanFull(),
            ]);
    }
}