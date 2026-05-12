<?php

namespace App\Filament\Resources\TripLogs\Schemas;

use App\Models\TripLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TripLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('trips.infolist.summary'))
                    ->schema([
                        TextEntry::make('vehicle_display')
                            ->label(__('trips.table.vehicle'))
                            ->state(fn (TripLog $record) => $record->vehicle?->nickname ?: ($record->vehicle?->brand.' '.$record->vehicle?->model)),
                        TextEntry::make('status')
                            ->label(__('trips.table.status'))
                            ->badge()
                            ->color(fn (string $state): string => TripLog::statusColor($state)),
                        TextEntry::make('started_at')
                            ->label(__('trips.infolist.started_at'))
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('ended_at')
                            ->label(__('trips.infolist.ended_at'))
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('distance_km')
                            ->label(__('trips.infolist.distance'))
                            ->state(fn (TripLog $record) => $record->distance_km !== null ? number_format((float) $record->distance_km, 2, ',', '.').' km' : '-'),
                        TextEntry::make('duration_seconds')
                            ->label(__('trips.infolist.duration'))
                            ->state(fn (TripLog $record) => $record->duration_seconds !== null ? gmdate('H:i:s', (int) $record->duration_seconds) : '-'),
                        TextEntry::make('points_count')
                            ->label(__('trips.infolist.points_count'))
                            ->placeholder('-'),
                        TextEntry::make('source_file_name')
                            ->label(__('trips.infolist.source_file'))
                            ->placeholder('-'),
                        TextEntry::make('failure_reason')
                            ->label(__('trips.infolist.failure_reason'))
                            ->placeholder('-')
                            ->visible(fn (TripLog $record): bool => $record->status === TripLog::STATUS_FAILED)
                            ->columnSpanFull(),
                    ])
                    ->columns(4),
                Section::make(__('trips.infolist.route'))
                    ->schema([
                        ViewEntry::make('route_map')
                            ->hiddenLabel()
                            ->view('filament.resources.trip-logs.route-map'),
                    ]),
            ]);
    }
}
