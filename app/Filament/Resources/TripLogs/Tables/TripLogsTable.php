<?php

namespace App\Filament\Resources\TripLogs\Tables;

use App\Models\TripLog;
use App\Services\Trips\TripLogProcessingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class TripLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label(__('trips.table.vehicle'))
                    ->formatStateUsing(fn ($state, TripLog $record) => $record->vehicle?->nickname ?: ($record->vehicle?->brand.' '.$record->vehicle?->model))
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('trips.table.title'))
                    ->placeholder(__('trips.table.no_title'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('ridden_at')
                    ->label(__('trips.table.ridden_at'))
                    ->date('d-m-Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('source_file_path')
                    ->label(__('trips.table.gpx'))
                    ->boolean()
                    ->state(fn (TripLog $record): bool => filled($record->source_file_path)),
                Tables\Columns\TextColumn::make('distance_km')
                    ->label(__('trips.table.distance'))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.').' km' : __('trips.table.not_processed'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('photos_count')
                    ->label(__('trips.table.photos'))
                    ->state(fn (TripLog $record): int => count($record->photos ?? [])),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('trips.table.status'))
                    ->badge()
                    ->color(fn (string $state): string => TripLog::statusColor($state)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('trips.table.created_at'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('ridden_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('reprocess')
                    ->label(__('trips.actions.reprocess'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (TripLog $record): bool => $record->canBeReprocessed())
                    ->action(function (TripLog $record, TripLogProcessingService $processingService): void {
                        $processingService->reprocess($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
