<?php

namespace App\Models;

use App\Services\PublicGarageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Vehicle extends Model
{
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
        'year',
        'public_slug',
        'is_public',
        'share_costs_publicly',
        'share_attachments_publicly',
        'purchase_price',
        'insurance_cost_per_month',
        'road_tax_cost_per_month',
        'notes',
        'photo',
        'photos',
        'media_attachments',
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

    private static function normalizeDecimal(mixed $value): ?string
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

        return number_format((float) $value, 2, '.', '');
    }
}
