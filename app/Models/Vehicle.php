<?php

namespace App\Models;

use App\Services\PublicGarageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Vehicle extends Model
{
    public const POWERTRAIN_PETROL = 'petrol';

    public const POWERTRAIN_DIESEL = 'diesel';

    public const POWERTRAIN_HYBRID = 'hybrid';

    public const POWERTRAIN_PHEV = 'phev';

    public const POWERTRAIN_ELECTRIC = 'electric';

    public const POWERTRAIN_LPG = 'lpg';

    public const POWERTRAIN_OTHER = 'other';

    protected $fillable = [
        'user_id',
        'airtable_record_id',
        'airtable_synced_at',
        'brand',
        'model',
        'display_variant',
        'nickname',
        'license_plate',
        'current_km',
        'distance_unit',
        'powertrain_type',
        'year',
        'public_slug',
        'is_public',
        'share_costs_publicly',
        'share_attachments_publicly',
        'purchase_price',
        'insurance_cost_per_month',
        'road_tax_cost_per_month',
        'home_kwh_rate',
        'notes',
        'photo',
        'photos',
        'media_attachments',
    ];

    protected $attributes = [
        'is_public' => true,
    ];

    protected $casts = [
        'photos' => 'array',
        'media_attachments' => 'array',
        'airtable_synced_at' => 'datetime',
        'is_public' => 'boolean',
        'purchase_price' => 'decimal:2',
        'share_attachments_publicly' => 'boolean',
        'share_costs_publicly' => 'boolean',
        'insurance_cost_per_month' => 'decimal:2',
        'road_tax_cost_per_month' => 'decimal:2',
        'home_kwh_rate' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::saving(function (Vehicle $vehicle): void {
            if (! Schema::hasColumn($vehicle->getTable(), 'public_slug') || filled($vehicle->public_slug)) {
                return;
            }

            $vehicle->public_slug = app(PublicGarageService::class)->generatePublicSlug(
                $vehicle,
                $vehicle->exists ? $vehicle->id : null,
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function tripLogs(): HasMany
    {
        return $this->hasMany(TripLog::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function setPurchasePriceAttribute(mixed $value): void
    {
        $this->attributes['purchase_price'] = self::normalizeDecimal($value);
    }

    public function setInsuranceCostPerMonthAttribute(mixed $value): void
    {
        $this->attributes['insurance_cost_per_month'] = self::normalizeDecimal($value);
    }

    public function setRoadTaxCostPerMonthAttribute(mixed $value): void
    {
        $this->attributes['road_tax_cost_per_month'] = self::normalizeDecimal($value);
    }

    public function setHomeKwhRateAttribute(mixed $value): void
    {
        $this->attributes['home_kwh_rate'] = self::normalizeDecimal($value, 3);
    }

    public function setPowertrainTypeAttribute(mixed $value): void
    {
        $this->attributes['powertrain_type'] = self::normalizePowertrainType($value);
    }

    public function isElectric(): bool
    {
        return $this->powertrain_type === self::POWERTRAIN_ELECTRIC;
    }

    public function isPhev(): bool
    {
        return $this->powertrain_type === self::POWERTRAIN_PHEV;
    }

    public function supportsChargingEntries(): bool
    {
        return $this->isElectric() || $this->isPhev();
    }

    public function usesFuelFlow(): bool
    {
        return ! $this->isElectric();
    }

    public static function powertrainOptions(): array
    {
        return [
            self::POWERTRAIN_PETROL => 'Benzine',
            self::POWERTRAIN_DIESEL => 'Diesel',
            self::POWERTRAIN_HYBRID => 'Hybride',
            self::POWERTRAIN_PHEV => 'Plug-in hybride',
            self::POWERTRAIN_ELECTRIC => 'Elektrisch',
            self::POWERTRAIN_LPG => 'LPG',
            self::POWERTRAIN_OTHER => 'Overig',
        ];
    }

    public static function normalizePowertrainType(mixed $value): string
    {
        $value = is_string($value) ? $value : null;

        return array_key_exists($value, self::powertrainOptions())
            ? $value
            : self::POWERTRAIN_PETROL;
    }

    private static function normalizeDecimal(mixed $value, int $precision = 2): ?string
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
