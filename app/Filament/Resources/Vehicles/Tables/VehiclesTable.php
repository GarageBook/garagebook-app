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
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->size(80),

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
                    ->label('KM'),

                Tables\Columns\TextColumn::make('year')
                    ->label('Bouwjaar'),

                Tables\Columns\TextColumn::make('media_attachments_count')
                    ->label('Bestanden')
                    ->getStateUsing(fn ($record) => is_array($record->media_attachments) ? count($record->media_attachments) : 0)
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ]);
    }
}
