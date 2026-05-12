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

                Tables\Columns\TextColumn::make('fuel_liters')
                    ->label(__('fuel.table.fueled'))
                    ->formatStateUsing(fn ($state, FuelLog $record): HtmlString => self::renderMeasurementCell(
                        ...self::fuelDisplay((float) $state, $record)
                    ))
                    ->html()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('average_consumption')
                    ->label(__('fuel.table.consumption'))
                    ->state(fn (FuelLog $record): HtmlString => self::renderConsumptionCell($record))
                    ->html()
                    ->extraAttributes(self::cellAttributes()),

                Tables\Columns\TextColumn::make('price_per_liter')
                    ->label(__('fuel.table.price_per_liter'))
                    ->formatStateUsing(fn ($state) => $state !== null ? 'EUR ' . number_format((float) $state, 3, ',', '.') : __('fuel.table.not_filled'))
                    ->toggleable()
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
            number_format((float) $miles, 0, ',', '.') . ' mi',
            number_format(round($kilometers), 0, ',', '.') . ' km',
        ];
    }

    private static function fuelDisplay(?float $liters, FuelLog $record): array
    {
        if ($liters === null || $liters <= 0) {
            return [__('fuel.table.not_filled'), null];
        }

        $primary = number_format($liters, 2, ',', '.') . ' L';
        $usesMiles = app(DistanceUnitService::class)->normalizeUnit($record->vehicle?->distance_unit) === DistanceUnitService::UNIT_MILES;

        if (! $usesMiles) {
            return [$primary, null];
        }

        $gallons = app(FuelConsumptionService::class)->convertLitersToUsGallons($liters);

        return [
            $primary,
            number_format((float) $gallons, 2, ',', '.') . ' gal (US)',
        ];
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
        $litersPer100Km = $fuelConsumption->calculateLitersPer100Km((float) $record->distance_km, (float) $record->fuel_liters);
        $ratio = $fuelConsumption->calculateRoundedKilometersPerLiterRatio((float) $record->distance_km, (float) $record->fuel_liters);
        $mpg = $usesMiles ? $fuelConsumption->calculateMilesPerUsGallon((float) $record->distance_km, (float) $record->fuel_liters) : null;

        return new HtmlString((string) view('filament.resources.fuel-logs.tables.consumption-cell', [
            'mpgLabel' => $mpg !== null ? number_format($mpg, 1, ',', '.') : null,
            'litersPer100KmLabel' => $litersPer100Km !== null
                ? number_format($litersPer100Km, 2, ',', '.') . ' L/100 km'
                : __('fuel.table.not_filled'),
            'ratioLabel' => $ratio !== null ? '1:' . $ratio : null,
            'distanceLabel' => number_format(round((float) $record->distance_km), 0, ',', '.') . ' km',
        ]));
    }

    private static function cellAttributes(): array
    {
        return [
            'style' => 'padding-top: 18px; padding-bottom: 18px; vertical-align: middle;',
        ];
    }
}
