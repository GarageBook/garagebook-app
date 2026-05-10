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
                Section::make(__('fuel.form.section_title'))
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label(__('fuel.form.vehicle'))
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
                            ->label(__('fuel.form.distance_unit'))
                            ->options(app(DistanceUnitService::class)->getSupportedUnits())
                            ->default(fn (): string => app(DistanceUnitService::class)->resolveForVehicleId(request()->integer('vehicle_id') ?: null))
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->helperText(__('fuel.form.distance_unit_help')),

                        Forms\Components\DatePicker::make('fuel_date')
                            ->label(__('fuel.form.date'))
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('odometer_km')
                            ->label(__('fuel.form.odometer'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('distance_km')
                            ->label(__('fuel.form.distance'))
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
                                '<span style="display:block; font-size:0.74rem; line-height:1.35; color:rgb(107, 114, 128);">' . e(__('fuel.form.distance_required_hint')) . '</span>'
                            ))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('fuel_liters')
                            ->label(__('fuel.form.liters'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->required()
                            ->suffix('L'),

                        Forms\Components\TextInput::make('price_per_liter')
                            ->label(__('fuel.form.price_per_liter'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('EUR'),

                        Forms\Components\TextInput::make('station_location')
                            ->label(__('fuel.form.station_location'))
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('consumption_preview')
                            ->label(__('fuel.form.consumption_preview'))
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
                            ->label(__('fuel.form.cost_preview'))
                            ->content(function (Get $get): string {
                                $totalCost = app(FuelConsumptionService::class)->calculateTotalCost(
                                    self::floatOrNull($get('fuel_liters')),
                                    self::floatOrNull($get('price_per_liter'))
                                );

                                if ($totalCost === null) {
                                    return __('fuel.form.worked_unknown');
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
            . e(__('fuel.form.miles_conversion', [
                'miles' => $milesLabel,
                'kilometers' => number_format((float) $distanceKm, 1, ',', '.'),
            ]))
            . '</strong>'
        );
    }

    private static function showsDistanceConversionHint(Get $get): bool
    {
        return self::floatOrNull($get('distance_km')) !== null
            && app(DistanceUnitService::class)->normalizeUnit($get('distance_unit')) === DistanceUnitService::UNIT_MILES;
    }
}
