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
                Section::make(fn (Get $get): string => self::sectionTitle($get))
                    ->schema([
                        Forms\Components\Select::make('vehicle_id')
                            ->label(__('fuel.form.vehicle'))
                            ->options(
                                Vehicle::query()
                                    ->where('user_id', auth()->id())
                                    ->latest()
                                    ->get()
                                    ->mapWithKeys(fn (Vehicle $vehicle) => [
                                        $vehicle->id => $vehicle->nickname ?: ($vehicle->brand.' '.$vehicle->model),
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->default(fn () => request()->integer('vehicle_id') ?: null)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $vehicleId = $state ? (int) $state : null;
                                $set('distance_unit', app(DistanceUnitService::class)->resolveForVehicleId($vehicleId));
                                $set('entry_type', self::defaultEntryTypeForVehicleId($vehicleId));
                                self::syncDerivedDistance($set, $get, $vehicleId);
                                self::syncHomeRate($set, $get);
                                self::syncTotalCost($set, $get);
                            }),

                        Forms\Components\Select::make('entry_type')
                            ->label(__('fuel.form.entry_type'))
                            ->options(FuelLog::entryTypeOptions())
                            ->default(fn (Get $get): string => self::defaultEntryTypeForVehicleId(self::intOrNull($get('vehicle_id')) ?: request()->integer('vehicle_id') ?: null))
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live()
                            ->visible(fn (Get $get): bool => self::isPhevSelected($get))
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncTotalCost($set, $get)),

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
                            ->required()
                            ->suffix(fn (Get $get): string => app(DistanceUnitService::class)->getUnitSuffix($get('distance_unit')))
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncDerivedDistance($set, $get, self::intOrNull($get('vehicle_id')))),

                        Forms\Components\TextInput::make('distance_km')
                            ->label(__('fuel.form.distance'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->required(fn (Get $get): bool => self::showsFuelFields($get))
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
                                '<span style="display:block; font-size:0.74rem; line-height:1.35; color:rgb(107, 114, 128);">'.e(__('fuel.form.distance_required_hint')).'</span>'
                            ))
                            ->visible(fn (Get $get): bool => self::showsFuelFields($get))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('fuel_liters')
                            ->label(__('fuel.form.liters'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->required(fn (Get $get): bool => self::showsFuelFields($get))
                            ->visible(fn (Get $get): bool => self::showsFuelFields($get))
                            ->live()
                            ->suffix('L')
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncTotalCost($set, $get)),

                        Forms\Components\TextInput::make('price_per_liter')
                            ->label(__('fuel.form.price_per_liter'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->visible(fn (Get $get): bool => self::showsFuelFields($get))
                            ->live()
                            ->prefix('EUR')
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncTotalCost($set, $get)),

                        Forms\Components\TextInput::make('energy_kwh')
                            ->label(__('fuel.form.energy_kwh'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->required(fn (Get $get): bool => self::showsChargeFields($get))
                            ->visible(fn (Get $get): bool => self::showsChargeFields($get))
                            ->live()
                            ->suffix('kWh')
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                self::syncHomeRate($set, $get);
                                self::syncTotalCost($set, $get);
                            }),

                        Forms\Components\TextInput::make('price_per_kwh')
                            ->label(__('fuel.form.price_per_kwh'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->visible(fn (Get $get): bool => self::showsChargeFields($get))
                            ->live()
                            ->prefix('EUR')
                            ->suffix('kWh')
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::syncTotalCost($set, $get)),

                        Forms\Components\Select::make('charge_type')
                            ->label(__('fuel.form.charge_type'))
                            ->options(FuelLog::chargeTypeOptions())
                            ->visible(fn (Get $get): bool => self::showsChargeFields($get))
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get): void {
                                self::syncHomeRate($set, $get);
                                self::syncTotalCost($set, $get);
                            }),

                        Forms\Components\TextInput::make('total_cost')
                            ->label(fn (Get $get): string => self::showsChargeFields($get) && ! self::showsFuelFields($get)
                                ? __('fuel.form.total_charge_cost')
                                : __('fuel.form.total_cost'))
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('EUR')
                            ->visible(fn (Get $get): bool => self::showsFuelFields($get) || self::showsChargeFields($get)),

                        Forms\Components\TextInput::make('station_location')
                            ->label(fn (Get $get): string => self::showsChargeFields($get) && ! self::showsFuelFields($get)
                                ? __('fuel.form.charge_location')
                                : __('fuel.form.station_location'))
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('fuel.form.notes'))
                            ->visible(fn (Get $get): bool => self::showsChargeFields($get))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('consumption_preview')
                            ->label(fn (Get $get): string => self::showsChargeFields($get) && ! self::showsFuelFields($get)
                                ? __('fuel.form.ev_consumption_preview')
                                : __('fuel.form.consumption_preview'))
                            ->content(function (Get $get): string {
                                $distanceKm = app(DistanceUnitService::class)->toKilometers(
                                    self::floatOrNull($get('distance_km')),
                                    $get('distance_unit'),
                                    1
                                );

                                if (self::showsChargeFields($get) && ! self::showsFuelFields($get)) {
                                    return app(FuelConsumptionService::class)->formatKwhPer100Km(
                                        $distanceKm,
                                        self::floatOrNull($get('energy_kwh'))
                                    );
                                }

                                return app(FuelConsumptionService::class)->formatAverage(
                                    $distanceKm,
                                    self::floatOrNull($get('fuel_liters')),
                                    auth()->user()?->consumption_unit
                                );
                            })
                            ->visible(fn (Get $get): bool => self::showsFuelFields($get) || self::showsChargeFields($get)),

                        Forms\Components\Placeholder::make('cost_preview')
                            ->label(__('fuel.form.cost_preview'))
                            ->content(function (Get $get): string {
                                $totalCost = self::calculatedTotalCost($get);

                                if ($totalCost === null) {
                                    return __('fuel.form.worked_unknown');
                                }

                                return 'EUR '.number_format($totalCost, 2, ',', '.');
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    private static function sectionTitle(Get $get): string
    {
        if (self::showsChargeFields($get) && ! self::showsFuelFields($get)) {
            return __('fuel.form.charge_section_title');
        }

        if (self::showsChargeFields($get) && self::showsFuelFields($get)) {
            return __('fuel.form.combined_section_title');
        }

        return __('fuel.form.section_title');
    }

    private static function selectedVehicle(Get $get): ?Vehicle
    {
        $vehicleId = self::intOrNull($get('vehicle_id')) ?: request()->integer('vehicle_id') ?: null;

        if (! $vehicleId) {
            return null;
        }

        return Vehicle::query()
            ->where('user_id', auth()->id())
            ->find($vehicleId);
    }

    private static function isPhevSelected(Get $get): bool
    {
        return self::selectedVehicle($get)?->isPhev() ?? false;
    }

    private static function showsFuelFields(Get $get): bool
    {
        $vehicle = self::selectedVehicle($get);

        if (! $vehicle) {
            return true;
        }

        if ($vehicle->isElectric()) {
            return false;
        }

        if ($vehicle->isPhev()) {
            return in_array(self::entryType($get), [FuelLog::ENTRY_TYPE_FUEL, FuelLog::ENTRY_TYPE_COMBINED], true);
        }

        return true;
    }

    private static function showsChargeFields(Get $get): bool
    {
        $vehicle = self::selectedVehicle($get);

        if (! $vehicle) {
            return false;
        }

        if ($vehicle->isElectric()) {
            return true;
        }

        if ($vehicle->isPhev()) {
            return in_array(self::entryType($get), [FuelLog::ENTRY_TYPE_CHARGE, FuelLog::ENTRY_TYPE_COMBINED], true);
        }

        return false;
    }

    private static function entryType(Get $get): string
    {
        $vehicle = self::selectedVehicle($get);

        if ($vehicle?->isElectric()) {
            return FuelLog::ENTRY_TYPE_CHARGE;
        }

        return FuelLog::normalizeEntryType($get('entry_type'));
    }

    private static function defaultEntryTypeForVehicleId(?int $vehicleId): string
    {
        if (! $vehicleId) {
            return FuelLog::ENTRY_TYPE_FUEL;
        }

        $vehicle = Vehicle::query()
            ->where('user_id', auth()->id())
            ->find($vehicleId);

        return $vehicle?->isElectric() ? FuelLog::ENTRY_TYPE_CHARGE : FuelLog::ENTRY_TYPE_FUEL;
    }

    private static function syncHomeRate(Set $set, Get $get): void
    {
        if (! self::showsChargeFields($get) || $get('charge_type') !== FuelLog::CHARGE_TYPE_HOME) {
            return;
        }

        $homeRate = self::selectedVehicle($get)?->home_kwh_rate;

        if ($homeRate !== null && self::floatOrNull($get('price_per_kwh')) === null) {
            $set('price_per_kwh', (string) $homeRate);
        }
    }

    private static function syncTotalCost(Set $set, Get $get): void
    {
        $totalCost = self::calculatedTotalCost($get);

        if ($totalCost === null) {
            return;
        }

        $set('total_cost', number_format($totalCost, 2, '.', ''));
    }

    private static function calculatedTotalCost(Get $get): ?float
    {
        $fuelCost = null;
        $chargeCost = null;

        if (self::showsFuelFields($get)) {
            $fuelCost = app(FuelConsumptionService::class)->calculateTotalCost(
                self::floatOrNull($get('fuel_liters')),
                self::floatOrNull($get('price_per_liter'))
            );
        }

        if (self::showsChargeFields($get)) {
            $pricePerKwh = self::floatOrNull($get('price_per_kwh'));

            if ($get('charge_type') === FuelLog::CHARGE_TYPE_HOME && $pricePerKwh === null) {
                $pricePerKwh = self::floatOrNull(self::selectedVehicle($get)?->home_kwh_rate);
            }

            $chargeCost = app(FuelConsumptionService::class)->calculateTotalCost(
                self::floatOrNull($get('energy_kwh')),
                $pricePerKwh
            );
        }

        if ($fuelCost === null && $chargeCost === null) {
            return null;
        }

        return round((float) $fuelCost + (float) $chargeCost, 2);
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
            '<strong style="'.$smallStyle.' margin-bottom:2px; color:rgb(17, 24, 39);">'
            .e(__('fuel.form.miles_conversion', [
                'miles' => $milesLabel,
                'kilometers' => number_format((float) $distanceKm, 1, ',', '.'),
            ]))
            .'</strong>'
        );
    }

    private static function showsDistanceConversionHint(Get $get): bool
    {
        return self::floatOrNull($get('distance_km')) !== null
            && app(DistanceUnitService::class)->normalizeUnit($get('distance_unit')) === DistanceUnitService::UNIT_MILES;
    }
}
