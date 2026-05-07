<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    protected $fillable = [
        'vehicle_id',
        'title',
        'document_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'document_date',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'document_date' => 'date',
        'expires_at' => 'date',
    ];

    public const TYPE_OPTIONS = [
        'registration' => 'Kentekenbewijs',
        'insurance' => 'Verzekeringsdocument',
        'invoice' => 'Aankoopfactuur',
        'warranty' => 'Garantiebewijs',
        'manual' => 'Handleiding',
        'inspection' => 'Keuringsrapport',
        'maintenance_report' => 'Onderhoudsrapport',
        'other' => 'Overig document',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_OPTIONS[$this->document_type] ?? self::TYPE_OPTIONS['other'];
    }
}
