<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    public const ENTRY_TYPE_FUEL = 'fuel';

    public const ENTRY_TYPE_CHARGE = 'charge';

    public const ENTRY_TYPE_COMBINED = 'combined';

    public const CHARGE_TYPE_HOME = 'home';

    public const CHARGE_TYPE_PUBLIC_AC = 'public_ac';

    public const CHARGE_TYPE_FAST_DC = 'fast_dc';

    public const CHARGE_TYPE_OTHER = 'other';

    protected $fillable = [
        'vehicle_id',
        'entry_type',
        'fuel_date',
        'odometer_km',
        'distance_km',
        'fuel_liters',
        'energy_kwh',
        'price_per_liter',
        'price_per_kwh',
        'total_cost',
        'charge_type',
        'station_location',
        'notes',
    ];

    protected $casts = [
        'fuel_date' => 'date',
        'odometer_km' => 'decimal:1',
        'distance_km' => 'decimal:1',
        'fuel_liters' => 'decimal:2',
        'energy_kwh' => 'decimal:2',
        'price_per_liter' => 'decimal:3',
        'price_per_kwh' => 'decimal:3',
        'total_cost' => 'decimal:2',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function setEntryTypeAttribute(mixed $value): void
    {
        $this->attributes['entry_type'] = self::normalizeEntryType($value);
    }

    public function setChargeTypeAttribute(mixed $value): void
    {
        $value = is_string($value) ? $value : null;

        $this->attributes['charge_type'] = array_key_exists($value, self::chargeTypeOptions()) ? $value : null;
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

    public function setEnergyKwhAttribute(mixed $value): void
    {
        $this->attributes['energy_kwh'] = self::normalizeDecimal($value, 2);
    }

    public function setPricePerKwhAttribute(mixed $value): void
    {
        $this->attributes['price_per_kwh'] = self::normalizeDecimal($value, 3);
    }

    public function setTotalCostAttribute(mixed $value): void
    {
        $this->attributes['total_cost'] = self::normalizeDecimal($value, 2);
    }

    public function isFuelEntry(): bool
    {
        return in_array($this->entry_type, [self::ENTRY_TYPE_FUEL, self::ENTRY_TYPE_COMBINED], true);
    }

    public function isChargeEntry(): bool
    {
        return in_array($this->entry_type, [self::ENTRY_TYPE_CHARGE, self::ENTRY_TYPE_COMBINED], true);
    }

    public function knownTotalCost(): ?float
    {
        if ($this->total_cost !== null) {
            return (float) $this->total_cost;
        }

        $fuelCost = $this->fuel_liters !== null && $this->price_per_liter !== null
            ? (float) $this->fuel_liters * (float) $this->price_per_liter
            : null;
        $chargeCost = $this->energy_kwh !== null && $this->price_per_kwh !== null
            ? (float) $this->energy_kwh * (float) $this->price_per_kwh
            : null;

        if ($fuelCost === null && $chargeCost === null) {
            return null;
        }

        return round((float) $fuelCost + (float) $chargeCost, 2);
    }

    public static function entryTypeOptions(): array
    {
        return [
            self::ENTRY_TYPE_FUEL => 'Tankbeurt',
            self::ENTRY_TYPE_CHARGE => 'Laadmoment',
            self::ENTRY_TYPE_COMBINED => 'Gecombineerd',
        ];
    }

    public static function chargeTypeOptions(): array
    {
        return [
            self::CHARGE_TYPE_HOME => 'Thuis laden',
            self::CHARGE_TYPE_PUBLIC_AC => 'Openbaar AC',
            self::CHARGE_TYPE_FAST_DC => 'Snellader DC',
            self::CHARGE_TYPE_OTHER => 'Overig',
        ];
    }

    public static function normalizeEntryType(mixed $value): string
    {
        $value = is_string($value) ? $value : null;

        return array_key_exists($value, self::entryTypeOptions())
            ? $value
            : self::ENTRY_TYPE_FUEL;
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
