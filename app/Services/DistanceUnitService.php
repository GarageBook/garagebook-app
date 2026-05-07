<?php

namespace App\Services;

use App\Models\Vehicle;

class DistanceUnitService
{
    public const UNIT_KM = 'km';
    public const UNIT_MILES = 'miles';

    private const KM_PER_MILE = 1.609344;

    public function getSupportedUnits(): array
    {
        return [
            self::UNIT_KM => 'Kilometers (km)',
            self::UNIT_MILES => 'Miles (mi)',
        ];
    }

    public function normalizeUnit(?string $unit): string
    {
        return array_key_exists($unit, $this->getSupportedUnits())
            ? $unit
            : self::UNIT_KM;
    }

    public function getUnitSuffix(?string $unit): string
    {
        return $this->normalizeUnit($unit) === self::UNIT_MILES ? 'mi' : 'km';
    }

    public function resolveForVehicleId(?int $vehicleId): string
    {
        if (! $vehicleId) {
            return self::UNIT_KM;
        }

        return $this->normalizeUnit(
            Vehicle::query()
                ->whereKey($vehicleId)
                ->value('distance_unit')
        );
    }

    public function persistVehicleUnit(?int $vehicleId, ?string $unit): string
    {
        $unit = $this->normalizeUnit($unit);

        if ($vehicleId) {
            Vehicle::query()
                ->whereKey($vehicleId)
                ->update(['distance_unit' => $unit]);
        }

        return $unit;
    }

    public function fromKilometers(mixed $value, ?string $unit, int $precision = 1): ?float
    {
        $distance = $this->floatOrNull($value);

        if ($distance === null) {
            return null;
        }

        if ($this->normalizeUnit($unit) === self::UNIT_MILES) {
            return round($distance / self::KM_PER_MILE, $precision);
        }

        return round($distance, $precision);
    }

    public function toKilometers(mixed $value, ?string $unit, int $precision = 1): ?float
    {
        $distance = $this->floatOrNull($value);

        if ($distance === null) {
            return null;
        }

        if ($this->normalizeUnit($unit) === self::UNIT_MILES) {
            return round($distance * self::KM_PER_MILE, $precision);
        }

        return round($distance, $precision);
    }

    public function formatFromKilometers(mixed $value, ?string $unit, int $precision = 1): string
    {
        $distance = $this->fromKilometers($value, $unit, $precision);

        if ($distance === null) {
            return 'Onbekend';
        }

        return number_format($distance, $precision, ',', '.') . ' ' . $this->getUnitSuffix($unit);
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
