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
                    ->label('Voertuig')
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
                    ->label('Afstandseenheid')
                    ->options(app(DistanceUnitService::class)->getSupportedUnits())
                    ->default(fn (): string => app(DistanceUnitService::class)->resolveForVehicleId(request()->integer('vehicle_id') ?: null))
                    ->required()
                    ->selectablePlaceholder(false)
                    ->helperText('Wordt onthouden als standaard voor dit voertuig.'),

                Forms\Components\TextInput::make('description')
                    ->label('Omschrijving')
                    ->required(),

                Forms\Components\TextInput::make('km_reading')
                    ->label('Tellerstand')
                    ->numeric()
                    ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                    ->required(),

                Forms\Components\DatePicker::make('maintenance_date')
                    ->label('Onderhoudsdatum')
                    ->required(),

                Forms\Components\TextInput::make('cost')
                    ->label('Kosten')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\TextInput::make('worked_hours')
                    ->label('Gewerkte uren')
                    ->numeric()
                    ->maxValue(9999.99)
                    ->inputMode('decimal')
                    ->placeholder('bijv. 2.5')
                    ->suffix(' uur'),

                Forms\Components\FileUpload::make('attachments')
                    ->label('Foto\'s, video\'s en bestanden')
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
                    ->label('Notities')
                    ->columnSpanFull(),

                // 🔥 REMINDERS (FIXED PROPERLY)
                Section::make('Herinnering')
                    ->description('Laat GarageBook je helpen herinneren aan toekomstig onderhoud')
                    ->schema([

                        Forms\Components\Toggle::make('reminder_enabled')
                            ->label('Herinnering inschakelen')
                            ->reactive(), // 👈 DIT IS DE FIX

                        Forms\Components\TextInput::make('interval_months')
                            ->label('Interval (maanden)')
                            ->numeric()
                            ->placeholder('bijv. 12')
                            ->visible(fn ($get) => $get('reminder_enabled')),

                        Forms\Components\TextInput::make('interval_km')
                            ->label(fn (Get $get): string => 'Interval (' . app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')) . ')')
                            ->numeric()
                            ->placeholder('bijv. 6000')
                            ->visible(fn ($get) => $get('reminder_enabled')),

                    ])
                    ->collapsed(),
            ]);
    }
}
