<?php

namespace App\Filament\Resources\MaintenanceLogs\Schemas;

use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class MaintenanceLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Forms\Components\Select::make('vehicle_id')
                    ->label(__('maintenance.fields.vehicle'))
                    ->options(
                        Vehicle::where('user_id', auth()->id())
                            ->get()
                            ->mapWithKeys(function ($vehicle) {
                                return [
                                    $vehicle->id => $vehicle->nickname
                                        ?: $vehicle->brand . ' ' . $vehicle->model,
                                ];
                            })
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                        'distance_unit',
                        app(DistanceUnitService::class)->resolveForVehicleId($state ? (int) $state : null)
                    )),

                Forms\Components\Select::make('distance_unit')
                    ->label(__('maintenance.fields.distance_unit'))
                    ->options(app(DistanceUnitService::class)->getSupportedUnits())
                    ->default(fn (): string => app(DistanceUnitService::class)->resolveForVehicleId(request()->integer('vehicle_id') ?: null))
                    ->required()
                    ->selectablePlaceholder(false)
                    ->helperText(__('maintenance.fields.distance_unit_help')),

                Forms\Components\TextInput::make('description')
                    ->label(__('maintenance.fields.description'))
                    ->required(),

                Forms\Components\TextInput::make('km_reading')
                    ->label(__('maintenance.fields.odometer'))
                    ->numeric()
                    ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                    ->required(),

                Forms\Components\DatePicker::make('maintenance_date')
                    ->label(__('maintenance.fields.maintenance_date'))
                    ->required(),

                Forms\Components\TextInput::make('cost')
                    ->label(__('maintenance.fields.cost'))
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\TextInput::make('worked_hours')
                    ->label(__('maintenance.fields.worked_hours'))
                    ->numeric()
                    ->maxValue(9999.99)
                    ->inputMode('decimal')
                    ->placeholder(__('maintenance.fields.worked_hours_placeholder'))
                    ->suffix(__('maintenance.table.hours_suffix')),

                Forms\Components\FileUpload::make('attachments')
                    ->label(__('maintenance.fields.attachments'))
                    ->disk('public')
                    ->directory('maintenance-attachments')
                    ->visibility('public')
                    ->fetchFileInformation(false)
                    ->maxSize(102400)
                    ->multiple()
                    ->appendFiles()
                    ->reorderable()
                    ->downloadable()
                    ->openable()
                    ->previewable(true)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label(__('maintenance.fields.notes'))
                    ->columnSpanFull(),

                Section::make(__('maintenance.reminder.section'))
                    ->description(__('maintenance.reminder.description'))
                    ->schema([

                        Forms\Components\Toggle::make('reminder_enabled')
                            ->label(__('maintenance.reminder.enabled'))
                            ->reactive(),

                        Forms\Components\TextInput::make('interval_months')
                            ->label(__('maintenance.reminder.interval_months'))
                            ->numeric()
                            ->placeholder(__('maintenance.reminder.interval_months_placeholder'))
                            ->visible(fn ($get) => $get('reminder_enabled')),

                        Forms\Components\TextInput::make('interval_km')
                            ->label(fn (Get $get): string => __('maintenance.reminder.interval_distance', [
                                'unit' => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')),
                            ]))
                            ->numeric()
                            ->placeholder(__('maintenance.reminder.interval_distance_placeholder'))
                            ->visible(fn ($get) => $get('reminder_enabled')),

                    ])
                    ->collapsed(),
            ]);
    }
}
