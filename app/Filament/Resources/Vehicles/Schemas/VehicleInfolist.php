<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('vehicles.model_label'))
                    ->schema([
                        TextEntry::make('nickname')
                            ->label(__('vehicles.fields.nickname'))
                            ->placeholder('-'),
                        TextEntry::make('brand')
                            ->label(__('vehicles.fields.brand'))
                            ->placeholder('-'),
                        TextEntry::make('model')
                            ->label(__('vehicles.fields.model'))
                            ->placeholder('-'),
                        TextEntry::make('license_plate')
                            ->label(__('vehicles.fields.license_plate'))
                            ->placeholder('-'),
                        TextEntry::make('current_km')
                            ->label(__('vehicles.fields.current_km'))
                            ->state(fn (Vehicle $record) => app(DistanceUnitService::class)->formatFromKilometers(
                                $record->current_km,
                                $record->distance_unit,
                                0
                            )),
                        TextEntry::make('year')
                            ->label(__('vehicles.fields.year'))
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->label(__('vehicles.fields.notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
