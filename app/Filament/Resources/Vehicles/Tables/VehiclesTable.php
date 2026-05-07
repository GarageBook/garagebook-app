<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Tables;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('maintenanceLogs:id,vehicle_id,attachments,media_attachments,file_attachments'))
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
                    ->label('Afstand')
                    ->formatStateUsing(fn ($state, Vehicle $record) => app(DistanceUnitService::class)->formatFromKilometers(
                        $state,
                        $record->distance_unit,
                        0
                    )),

                Tables\Columns\TextColumn::make('year')
                    ->label('Bouwjaar'),

                Tables\Columns\TextColumn::make('media_attachments_count')
                    ->label('Bestanden')
                    ->getStateUsing(fn (Vehicle $record) => self::fileCount($record))
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

    public static function fileCount(Vehicle $vehicle): int
    {
        $vehicleFiles = count(array_filter([
            $vehicle->photo,
            ...(is_array($vehicle->photos) ? $vehicle->photos : []),
            ...(is_array($vehicle->media_attachments) ? $vehicle->media_attachments : []),
        ]));

        $maintenanceFiles = $vehicle->maintenanceLogs->sum(
            fn ($log) => count($log->attachments)
        );

        return $vehicleFiles + $maintenanceFiles;
    }
}
