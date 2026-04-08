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
                Tables\Columns\ImageColumn::make('attachments')
                    ->label('Foto')
                    ->disk('public')
                    ->width(180)
                    ->height(100)
                    ->getStateUsing(function ($record) {
                        return is_array($record->attachments) && count($record->attachments)
                            ? $record->attachments[0]
                            : null;
                    }),

                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label('Voertuig')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Omschrijving')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('km_reading')
                    ->label('KM')
                    ->suffix(' km')
                    ->badge(),

                Tables\Columns\TextColumn::make('maintenance_date')
                    ->label('Datum')
                    ->date('d-m-Y')
                    ->badge(),

                Tables\Columns\TextColumn::make('cost')
                    ->label('Kosten')
                    ->money('EUR')
                    ->badge(),
            ])
            ->defaultSort('maintenance_date', 'desc')
            ->striped()
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