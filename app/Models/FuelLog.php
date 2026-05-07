<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'fuel_date',
        'odometer_km',
        'distance_km',
        'fuel_liters',
        'price_per_liter',
        'station_location',
    ];

    protected $casts = [
        'fuel_date' => 'date',
        'odometer_km' => 'decimal:1',
        'distance_km' => 'decimal:1',
        'fuel_liters' => 'decimal:2',
        'price_per_liter' => 'decimal:3',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function setOdometerKmAttribute(mixed $value): void
    {
        $this->attributes['odometer_km'] = self::normalizeDecimal($value, 1);
    }

    public function setDistanceKmAttribute(mixed $value): void
    {
        $this->attributes['distance_km'] = self::normalizeDecimal($value, 1);
    }

    public function setFuelLitersAttribute(mixed $value): void
    {
        $this->attributes['fuel_liters'] = self::normalizeDecimal($value, 2);
    }

    public function setPricePerLiterAttribute(mixed $value): void
    {
        $this->attributes['price_per_liter'] = self::normalizeDecimal($value, 3);
    }

    private static function normalizeDecimal(mixed $value, int $precision): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(['EUR', '€', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, $precision, '.', '');
    }
}
