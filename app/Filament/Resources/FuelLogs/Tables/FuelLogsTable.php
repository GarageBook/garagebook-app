<?php

namespace App\Filament\Resources\FuelLogs\Tables;

use App\Models\FuelLog;
use App\Services\DistanceUnitService;
use App\Services\FuelConsumptionService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class FuelLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fuel_date')
                    ->label(__('fuel.table.date'))
                    ->date('d-m-Y')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label(__('fuel.table.vehicle'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('odometer_km')
                    ->label(__('fuel.table.odometer'))
                    ->formatStateUsing(fn ($state, FuelLog $record) => $state !== null
                        ? app(DistanceUnitService::class)->formatFromKilometers($state, $record->vehicle?->distance_unit, 1)
                        : __('fuel.table.not_filled'))
                    ->badge(),

                Tables\Columns\TextColumn::make('distance_km')
                    ->label(__('fuel.table.distance'))
                    ->formatStateUsing(fn ($state, FuelLog $record) => app(DistanceUnitService::class)->formatFromKilometers(
                        $state,
                        $record->vehicle?->distance_unit,
                        1
                    ))
                    ->badge(),

                Tables\Columns\TextColumn::make('fuel_liters')
                    ->label(__('fuel.table.liters'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.') . ' L')
                    ->badge(),

                Tables\Columns\TextColumn::make('average_consumption')
                    ->label(__('fuel.table.consumption'))
                    ->state(fn (FuelLog $record) => app(FuelConsumptionService::class)->formatAverage(
                        (float) $record->distance_km,
                        (float) $record->fuel_liters,
                        auth()->user()?->consumption_unit
                    ))
                    ->badge(),

                Tables\Columns\TextColumn::make('price_per_liter')
                    ->label(__('fuel.table.price_per_liter'))
                    ->formatStateUsing(fn ($state) => $state !== null ? 'EUR ' . number_format((float) $state, 3, ',', '.') : __('fuel.table.not_filled'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('station_location')
                    ->label(__('fuel.table.location'))
                    ->searchable()
                    ->wrap(),
            ])
            ->defaultSort('fuel_date', 'desc')
            ->striped()
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
