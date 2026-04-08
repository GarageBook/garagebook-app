<?php

namespace App\Filament\Resources\Vehicles\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Tables;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand')
                    ->label('Merk')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('license_plate')
                    ->label('Kenteken'),

                Tables\Columns\TextColumn::make('current_km')
                    ->label('Huidige kilometerstand'),

                Tables\Columns\TextColumn::make('year')
                    ->label('Bouwjaar'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ]);
    }
}