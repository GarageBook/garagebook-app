<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'description',
        'km_reading',
        'maintenance_date',
        'cost',
        'worked_hours',
        'attachment',
        'attachments',
        'notes',

        // reminders
        'interval_months',
        'interval_km',
        'reminder_enabled',
        'last_km',
        'last_date',
    ];

    protected $casts = [
        'attachments' => 'array',
        'maintenance_date' => 'date',
        'last_date' => 'date',
        'worked_hours' => 'decimal:2',
        'reminder_enabled' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($log) {
            $log->last_km = $log->km_reading;
            $log->last_date = $log->maintenance_date;
        });
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
