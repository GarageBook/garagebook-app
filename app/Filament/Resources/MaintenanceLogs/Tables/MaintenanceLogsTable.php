<?php

namespace App\Filament\Resources\MaintenanceLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label('Voertuig')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Omschrijving')
                    ->searchable(),

                Tables\Columns\TextColumn::make('km_reading')
                    ->label('Kilometerstand'),

                Tables\Columns\TextColumn::make('maintenance_date')
                    ->label('Onderhoudsdatum')
                    ->date(),

                Tables\Columns\TextColumn::make('cost')
                    ->label('Kosten')
                    ->money('EUR'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}