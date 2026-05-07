<?php

namespace App\Filament\Resources\FuelLogs\Schemas;

use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Services\FuelConsumptionService;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class FuelLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tankbeurt')
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label('Voertuig')
                            ->options(
                                Vehicle::query()
                                    ->where('user_id', auth()->id())
                                    ->latest()
                                    ->get()
                                    ->mapWithKeys(fn (Vehicle $vehicle) => [
                                        $vehicle->id => $vehicle->nickname ?: ($vehicle->brand . ' ' . $vehicle->model),
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->default(fn () => request()->integer('vehicle_id') ?: null)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => self::syncDerivedDistance($set, $get, $state ? (int) $state : null)),

                        Forms\Components\DatePicker::make('fuel_date')
                            ->label('Datum')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('odometer_km')
                            ->label('Kilometerstand')
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix('km')
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('distance_km')
                            ->label('Aantal km gereden')
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->suffix('km')
                            ->helperText('Verplicht. Wordt automatisch voorgesteld zodra kilometerstand en een vorige tankbeurt bekend zijn, maar blijft handmatig aanpasbaar.'),

                        Forms\Components\TextInput::make('fuel_liters')
                            ->label('Aantal liter brandstof')
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->suffix('L'),

                        Forms\Components\TextInput::make('price_per_liter')
                            ->label('Prijs per liter')
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('EUR'),

                        Forms\Components\TextInput::make('station_location')
                            ->label('Locatie benzinepomp')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('consumption_preview')
                            ->label('Berekend verbruik')
                            ->content(function (Get $get): string {
                                $distanceKm = self::floatOrNull($get('distance_km'));
                                $fuelLiters = self::floatOrNull($get('fuel_liters'));

                                return app(FuelConsumptionService::class)->formatAverage(
                                    $distanceKm,
                                    $fuelLiters,
                                    auth()->user()?->consumption_unit
                                );
                            }),

                        Forms\Components\Placeholder::make('cost_preview')
                            ->label('Totale brandstofkosten')
                            ->content(function (Get $get): string {
                                $totalCost = app(FuelConsumptionService::class)->calculateTotalCost(
                                    self::floatOrNull($get('fuel_liters')),
                                    self::floatOrNull($get('price_per_liter'))
                                );

                                if ($totalCost === null) {
                                    return 'Onbekend';
                                }

                                return 'EUR ' . number_format($totalCost, 2, ',', '.');
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    private static function syncDerivedDistance(Set $set, Get $get, ?int $vehicleId): void
    {
        if (! $vehicleId) {
            return;
        }

        $suggestedDistance = app(FuelConsumptionService::class)->suggestDistanceKm(
            $vehicleId,
            $get('fuel_date'),
            self::floatOrNull($get('odometer_km')),
            self::intOrNull($get('id'))
        );

        if ($suggestedDistance === null) {
            return;
        }

        $set('distance_km', $suggestedDistance);
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private static function intOrNull(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
