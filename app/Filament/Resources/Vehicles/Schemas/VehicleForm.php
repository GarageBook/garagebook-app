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
                    ->label('Hoofdfoto')
                    ->image()
                    ->disk('public')
                    ->directory('vehicle-photos')
                    ->visibility('public')
                    ->maxSize(20480)
                    ->previewable(false)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('photos')
                    ->label('Fotogalerij')
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->disk('public')
                    ->directory('vehicle-photos')
                    ->visibility('public')
                    ->maxSize(20480)
                    ->previewable(false)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('media_attachments')
                    ->label('Overige media en bestanden')
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->directory('vehicle-attachments')
                    ->visibility('public')
                    ->maxSize(102400)
                    ->downloadable()
                    ->openable()
                    ->previewable(true)
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

                Forms\Components\TextInput::make('purchase_price')
                    ->label('Aanschafprijs')
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('EUR'),

                Forms\Components\TextInput::make('insurance_cost_per_month')
                    ->label('Kosten verzekering per maand')
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('EUR'),

                Forms\Components\TextInput::make('road_tax_cost_per_month')
                    ->label('Kosten wegenbelasting per maand')
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('EUR'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notities'),
            ]);
    }
}
