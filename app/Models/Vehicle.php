<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'user_id',
        'airtable_record_id',
        'airtable_synced_at',
        'brand',
        'model',
        'nickname',
        'license_plate',
        'current_km',
        'year',
        'notes',
        'photo',
        'photos',
        'media_attachments',
    ];

    protected $casts = [
        'photos' => 'array',
        'media_attachments' => 'array',
        'airtable_synced_at' => 'datetime',
    ];

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }
}
