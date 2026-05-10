<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Services\DistanceUnitService;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\FileUpload::make('photo')
                    ->label(__('vehicles.fields.photo'))
                    ->image()
                    ->disk('public')
                    ->directory('vehicle-photos')
                    ->visibility('public')
                    ->maxSize(20480)
                    ->previewable(false)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('photos')
                    ->label(__('vehicles.fields.photos'))
                    ->multiple()
                    ->reorderable()
                    ->image()
                    ->disk('public')
                    ->directory('vehicle-photos')
                    ->visibility('public')
                    ->maxSize(20480)
                    ->previewable(false)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('media_attachments')
                    ->label(__('vehicles.fields.media_attachments'))
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->directory('vehicle-attachments')
                    ->visibility('public')
                    ->maxSize(102400)
                    ->downloadable()
                    ->openable()
                    ->previewable(true)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('brand')
                    ->label(__('vehicles.fields.brand'))
                    ->required(),

                Forms\Components\TextInput::make('model')
                    ->label(__('vehicles.fields.model'))
                    ->required(),

                Forms\Components\TextInput::make('nickname')
                    ->label(__('vehicles.fields.nickname')),

                Forms\Components\TextInput::make('license_plate')
                    ->label(__('vehicles.fields.license_plate')),

                Forms\Components\Select::make('distance_unit')
                    ->label(__('vehicles.fields.distance_unit'))
                    ->options(app(DistanceUnitService::class)->getSupportedUnits())
                    ->default(DistanceUnitService::UNIT_KM)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live(),

                Forms\Components\TextInput::make('current_km')
                    ->label(__('vehicles.fields.current_km'))
                    ->numeric()
                    ->required()
                    ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit'))),

                Forms\Components\TextInput::make('year')
                    ->label(__('vehicles.fields.year'))
                    ->numeric(),

                Forms\Components\TextInput::make('purchase_price')
                    ->label(__('vehicles.fields.purchase_price'))
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('EUR'),

                Forms\Components\TextInput::make('insurance_cost_per_month')
                    ->label(__('vehicles.fields.insurance_cost_per_month'))
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('EUR'),

                Forms\Components\TextInput::make('road_tax_cost_per_month')
                    ->label(__('vehicles.fields.road_tax_cost_per_month'))
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('EUR'),

                Forms\Components\Textarea::make('notes')
                    ->label(__('vehicles.fields.notes')),
            ]);
    }
}
