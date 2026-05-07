<?php

namespace App\Filament\Resources\FuelLogs\Schemas;

use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use App\Services\FuelConsumptionService;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $vehicleId = $state ? (int) $state : null;
                                $set('distance_unit', app(DistanceUnitService::class)->resolveForVehicleId($vehicleId));
                                self::syncDerivedDistance($set, $get, $vehicleId);
                            }),

                        Forms\Components\Select::make('distance_unit')
                            ->label('Afstandseenheid')
                            ->options(app(DistanceUnitService::class)->getSupportedUnits())
                            ->default(fn (): string => app(DistanceUnitService::class)->resolveForVehicleId(request()->integer('vehicle_id') ?: null))
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->helperText('Wordt onthouden als standaard voor dit voertuig.'),

                        Forms\Components\DatePicker::make('fuel_date')
                            ->label('Datum')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('odometer_km')
                            ->label('Tellerstand')
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('distance_km')
                            ->label('Afgelegde afstand')
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->live()
                            ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                            ->helperText(null),

                        Forms\Components\Placeholder::make('distance_km_conversion_hint')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::distanceHelperText($get))
                            ->visible(fn (Get $get): bool => self::showsDistanceConversionHint($get))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('distance_km_description_hint')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(
                                '<span style="display:block; font-size:0.74rem; line-height:1.35; color:rgb(107, 114, 128);">Verplicht. Wordt automatisch voorgesteld zodra tellerstand en een vorige tankbeurt bekend zijn, maar blijft handmatig aanpasbaar.</span>'
                            ))
                            ->columnSpanFull(),

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
                                $distanceKm = app(DistanceUnitService::class)->toKilometers(
                                    self::floatOrNull($get('distance_km')),
                                    $get('distance_unit'),
                                    1
                                );
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
            app(DistanceUnitService::class)->toKilometers(
                self::floatOrNull($get('odometer_km')),
                $get('distance_unit'),
                1
            ),
            self::intOrNull($get('id'))
        );

        if ($suggestedDistance === null) {
            return;
        }

        $set('distance_km', app(DistanceUnitService::class)->fromKilometers(
            $suggestedDistance,
            $get('distance_unit'),
            1
        ));
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

    private static function distanceHelperText(Get $get): HtmlString
    {
        $distance = self::floatOrNull($get('distance_km'));

        $distanceKm = app(DistanceUnitService::class)->toKilometers($distance, DistanceUnitService::UNIT_MILES, 1);
        $milesLabel = rtrim(rtrim(number_format($distance, 1, ',', '.'), '0'), ',');
        $smallStyle = 'display:block; font-size:0.74rem; line-height:1.35; color:rgb(107, 114, 128);';

        return new HtmlString(
            '<strong style="' . $smallStyle . ' margin-bottom:2px; color:rgb(17, 24, 39);">'
            . e($milesLabel . ' miles is ' . number_format((float) $distanceKm, 1, ',', '.') . ' km.')
            . '</strong>'
        );
    }

    private static function showsDistanceConversionHint(Get $get): bool
    {
        return self::floatOrNull($get('distance_km')) !== null
            && app(DistanceUnitService::class)->normalizeUnit($get('distance_unit')) === DistanceUnitService::UNIT_MILES;
    }
}
