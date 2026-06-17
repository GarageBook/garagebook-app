<?php

namespace App\Filament\Resources\MaintenanceLogs\Schemas;

use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use App\Support\MaintenanceLogVehicleResolver;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class MaintenanceLogForm
{
    private const DESCRIPTION_SUGGESTIONS = [
        'Olie + filter vervangen',
        'Banden vervangen',
        'Remmen gecontroleerd',
        'Accu vervangen',
        'Algemene controle',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Snel onderhoud toevoegen')
                    ->description('Begin simpel. Je kunt later altijd foto\'s, facturen of details toevoegen.')
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label(__('maintenance.fields.vehicle'))
                            ->options(
                                Vehicle::where('user_id', auth()->id())
                                    ->get()
                                    ->mapWithKeys(function ($vehicle) {
                                        return [
                                            $vehicle->id => $vehicle->nickname
                                                ?: $vehicle->brand.' '.$vehicle->model,
                                        ];
                                    })
                            )
                            ->searchable()
                            ->required()
                            ->default(fn (): ?int => app(MaintenanceLogVehicleResolver::class)->resolveForUser(
                                auth()->user(),
                                request()->integer('vehicle_id') ?: null,
                            ))
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                'distance_unit',
                                app(DistanceUnitService::class)->resolveForVehicleId($state ? (int) $state : null)
                            )),

                        Forms\Components\Select::make('distance_unit')
                            ->label(__('maintenance.fields.distance_unit'))
                            ->options(app(DistanceUnitService::class)->getSupportedUnits())
                            ->default(fn (): string => app(DistanceUnitService::class)->resolveForVehicleId(
                                app(MaintenanceLogVehicleResolver::class)->resolveForUser(
                                    auth()->user(),
                                    request()->integer('vehicle_id') ?: null,
                                )
                            ))
                            ->required()
                            ->selectablePlaceholder(false)
                            ->helperText(__('maintenance.fields.distance_unit_help')),

                        Forms\Components\TextInput::make('description')
                            ->label(__('maintenance.fields.description'))
                            ->placeholder('bijv. Olie + filter vervangen')
                            ->datalist(self::DESCRIPTION_SUGGESTIONS)
                            ->helperText(__('maintenance.fields.description_help'))
                            ->required(),

                        Forms\Components\TextInput::make('km_reading')
                            ->label(__('maintenance.fields.odometer'))
                            ->numeric()
                            ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                            ->required(),

                        Forms\Components\DatePicker::make('maintenance_date')
                            ->label(__('maintenance.fields.maintenance_date'))
                            ->default(now()->toDateString())
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Extra details (optioneel)')
                    ->schema([
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

                        Forms\Components\Toggle::make('hide_photos_on_public_page')
                            ->label(__('maintenance.public_sharing.hide_photos_label'))
                            ->helperText(__('maintenance.public_sharing.hide_photos_help'))
                            ->default(false),

                        Forms\Components\Toggle::make('share_attachments_publicly')
                            ->label(__('maintenance.public_sharing.share_other_attachments_label'))
                            ->helperText(__('maintenance.public_sharing.share_other_attachments_help'))
                            ->default(false),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('maintenance.fields.notes'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

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
                    ->collapsed(! request()->boolean('with_reminder')),
            ]);
    }
}
