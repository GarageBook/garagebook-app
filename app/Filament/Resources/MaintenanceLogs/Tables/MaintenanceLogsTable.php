<?php

namespace App\Filament\Resources\MaintenanceLogs\Tables;

use App\Services\DistanceUnitService;
use App\Support\ImageThumbnail;
use App\Support\MediaPath;
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
                    ->label(__('maintenance.table.preview'))
                    ->disk('public')
                    ->width(180)
                    ->height(100)
                    ->extraImgAttributes([
                        'loading' => 'lazy',
                        'decoding' => 'async',
                        'fetchpriority' => 'low',
                    ])
                    ->getStateUsing(function ($record) {
                        foreach ($record->media_attachments as $attachment) {
                            if (MediaPath::isImage($attachment)) {
                                return ImageThumbnail::path($attachment, 240) ?: $attachment;
                            }
                        }

                        return null;
                    }),

                Tables\Columns\TextColumn::make('attachments_count')
                    ->label(__('maintenance.table.files'))
                    ->getStateUsing(fn ($record) => count($record->media_attachments) + count($record->file_attachments))
                    ->badge(),

                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label(__('maintenance.table.vehicle'))
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('maintenance.table.description'))
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('km_reading')
                    ->label(__('maintenance.table.distance'))
                    ->formatStateUsing(fn ($state, $record) => app(DistanceUnitService::class)->formatFromKilometers(
                        $state,
                        $record->vehicle?->distance_unit,
                        0
                    ))
                    ->badge(),

                Tables\Columns\TextColumn::make('maintenance_date')
                    ->label(__('maintenance.table.date'))
                    ->date('d-m-Y')
                    ->badge(),

                Tables\Columns\TextColumn::make('cost')
                    ->label(__('maintenance.table.cost'))
                    ->money('EUR')
                    ->badge(),

                Tables\Columns\TextColumn::make('worked_hours')
                    ->label(__('maintenance.table.hours'))
                    ->suffix(__('maintenance.table.hours_suffix'))
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
