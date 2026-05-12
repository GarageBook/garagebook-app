<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'title',
        'description',
        'source_file_path',
        'source_file_name',
        'source_format',
        'status',
        'failure_reason',
        'distance_km',
        'duration_seconds',
        'started_at',
        'ended_at',
        'points_count',
        'bounds',
        'geojson',
        'simplified_geojson',
        'stats',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'bounds' => 'array',
            'distance_km' => 'decimal:3',
            'ended_at' => 'datetime',
            'processed_at' => 'datetime',
            'started_at' => 'datetime',
            'stats' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_PROCESSING => 'info',
            self::STATUS_PROCESSED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function canBeReprocessed(): bool
    {
        return in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_PROCESSED,
        ], true);
    }
}
