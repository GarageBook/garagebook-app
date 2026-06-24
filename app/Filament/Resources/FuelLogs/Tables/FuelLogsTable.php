<?php

namespace App\Filament\Resources\FuelLogs\Tables;

use App\Models\FuelLog;
use App\Services\DistanceUnitService;
use App\Services\FuelConsumptionService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class FuelLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fuel_date')
                    ->label(__('fuel.table.date'))
                    ->formatStateUsing(fn ($state, FuelLog $record): string => $record->fuel_date?->format('d-m-Y') ?? __('fuel.table.not_filled'))
                    ->sortable()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label(__('fuel.table.vehicle'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('odometer_km')
                    ->label(__('fuel.table.odometer'))
                    ->formatStateUsing(fn ($state, FuelLog $record): HtmlString => self::renderMeasurementCell(
                        ...self::measurementDisplay((float) $state, $record, 'odometer'),
                        align: 'center'
                    ))
                    ->html()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('distance_km')
                    ->label(__('fuel.table.distance'))
                    ->formatStateUsing(fn ($state, FuelLog $record): HtmlString => self::renderMeasurementCell(
                        ...self::measurementDisplay((float) $state, $record, 'distance'),
                        align: 'center'
                    ))
                    ->html()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('entry_type')
                    ->label(__('fuel.table.entry_type'))
                    ->formatStateUsing(fn ($state): string => FuelLog::entryTypeOptions()[$state] ?? FuelLog::entryTypeOptions()[FuelLog::ENTRY_TYPE_FUEL])
                    ->badge()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('fuel_liters')
                    ->label(__('fuel.table.fueled'))
                    ->formatStateUsing(fn ($state, FuelLog $record): HtmlString => self::renderMeasurementCell(
                        ...self::energyDisplay($record)
                    ))
                    ->html()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('average_consumption')
                    ->label(__('fuel.table.consumption'))
                    ->state(fn (FuelLog $record): HtmlString => self::renderConsumptionCell($record))
                    ->html()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label(__('fuel.table.costs'))
                    ->state(fn (FuelLog $record): string => self::costDisplay($record))
                    ->toggleable()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('charge_type')
                    ->label(__('fuel.table.charge_type'))
                    ->formatStateUsing(fn ($state): string => $state ? (FuelLog::chargeTypeOptions()[$state] ?? __('fuel.table.not_filled')) : __('fuel.table.not_filled'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('station_location')
                    ->label(__('fuel.table.location'))
                    ->searchable()
                    ->wrap()
                    ->extraAttributes(self::cellAttributes()),
            ])
            ->defaultSort('fuel_date', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function measurementDisplay(?float $kilometers, FuelLog $record, string $type): array
    {
        if ($kilometers === null || $kilometers <= 0) {
            return [__('fuel.table.not_filled'), null];
        }

        $distanceUnit = app(DistanceUnitService::class);
        $usesMiles = $distanceUnit->normalizeUnit($record->vehicle?->distance_unit) === DistanceUnitService::UNIT_MILES;

        if (! $usesMiles) {
            return [$distanceUnit->formatFromKilometers($kilometers, DistanceUnitService::UNIT_KM, 1), null];
        }

        $fuelConsumption = app(FuelConsumptionService::class);
        $miles = $fuelConsumption->convertKilometersToMiles($kilometers);

        return [
            number_format((float) $miles, 0, ',', '.').' mi',
            number_format(round($kilometers), 0, ',', '.').' km',
        ];
    }

    private static function energyDisplay(FuelLog $record): array
    {
        $parts = [];

        if ($record->fuel_liters !== null && (float) $record->fuel_liters > 0) {
            $parts[] = number_format((float) $record->fuel_liters, 2, ',', '.').' L';
        }

        if ($record->energy_kwh !== null && (float) $record->energy_kwh > 0) {
            $parts[] = number_format((float) $record->energy_kwh, 2, ',', '.').' kWh';
        }

        if ($parts === []) {
            return [__('fuel.table.not_filled'), null];
        }

        $usesMiles = app(DistanceUnitService::class)->normalizeUnit($record->vehicle?->distance_unit) === DistanceUnitService::UNIT_MILES;

        if (! $usesMiles || $record->fuel_liters === null || (float) $record->fuel_liters <= 0) {
            return [implode(' + ', $parts), null];
        }

        $gallons = app(FuelConsumptionService::class)->convertLitersToUsGallons((float) $record->fuel_liters);

        return [
            implode(' + ', $parts),
            number_format((float) $gallons, 2, ',', '.').' gal (US)',
        ];
    }

    private static function costDisplay(FuelLog $record): string
    {
        $total = $record->knownTotalCost();

        if ($total === null) {
            return __('fuel.table.not_filled');
        }

        $label = 'EUR '.number_format($total, 2, ',', '.');

        if ($record->price_per_liter !== null) {
            $label .= ' (EUR '.number_format((float) $record->price_per_liter, 3, ',', '.').'/L)';
        }

        if ($record->price_per_kwh !== null) {
            $label .= ' (EUR '.number_format((float) $record->price_per_kwh, 3, ',', '.').'/kWh)';
        }

        return $label;
    }

    private static function renderMeasurementCell(string $primary, ?string $secondary = null, string $align = 'start'): HtmlString
    {
        return new HtmlString((string) view('filament.resources.fuel-logs.tables.measurement-cell', [
            'primary' => $primary,
            'secondary' => $secondary,
            'align' => $align,
        ]));
    }

    private static function renderConsumptionCell(FuelLog $record): HtmlString
    {
        $fuelConsumption = app(FuelConsumptionService::class);
        $distanceUnit = app(DistanceUnitService::class);
        $usesMiles = $distanceUnit->normalizeUnit($record->vehicle?->distance_unit) === DistanceUnitService::UNIT_MILES;
        $distanceKm = $record->distance_km !== null ? (float) $record->distance_km : null;
        $labels = [];

        if ($record->fuel_liters !== null && (float) $record->fuel_liters > 0) {
            $litersPer100Km = $fuelConsumption->calculateLitersPer100Km($distanceKm, (float) $record->fuel_liters);
            $ratio = $fuelConsumption->calculateRoundedKilometersPerLiterRatio($distanceKm, (float) $record->fuel_liters);
            $mpg = $usesMiles ? $fuelConsumption->calculateMilesPerUsGallon($distanceKm, (float) $record->fuel_liters) : null;
            $labels[] = $litersPer100Km !== null ? number_format($litersPer100Km, 2, ',', '.').' L/100 km' : __('fuel.table.not_filled');

            if ($ratio !== null) {
                $labels[] = '1:'.$ratio;
            }

            if ($mpg !== null) {
                $labels[] = number_format($mpg, 1, ',', '.').' MPG';
            }
        }

        if ($record->energy_kwh !== null && (float) $record->energy_kwh > 0) {
            $kwhPer100Km = $fuelConsumption->calculateKwhPer100Km($distanceKm, (float) $record->energy_kwh);
            $labels[] = $kwhPer100Km !== null ? number_format($kwhPer100Km, 2, ',', '.').' kWh/100 km' : __('fuel.table.not_filled');
        }

        return self::renderMeasurementCell(
            $labels !== [] ? implode(' / ', $labels) : __('fuel.table.not_filled'),
            $distanceKm !== null && $distanceKm > 0 ? number_format(round($distanceKm), 0, ',', '.').' km' : null
        );
    }

    private static function cellAttributes(): array
    {
        return [
            'style' => 'padding-top: 18px; padding-bottom: 18px; vertical-align: middle;',
        ];
    }
}
