<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'airtable_record_id',
        'description',
        'km_reading',
        'maintenance_date',
        'cost',
        'worked_hours',
        'attachment',
        'attachments',
        'media_attachments',
        'file_attachments',
        'notes',

        // reminders
        'interval_months',
        'interval_km',
        'reminder_enabled',
        'last_km',
        'last_date',
        'airtable_synced_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'media_attachments' => 'array',
        'file_attachments' => 'array',
        'airtable_synced_at' => 'datetime',
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

    public function getMediaAttachmentsAttribute($value): array
    {
        return $this->resolveSeparatedAttachments(
            $value,
            fn (string $path) => MediaPath::isImage($path) || MediaPath::isVideo($path)
        );
    }

    public function getFileAttachmentsAttribute($value): array
    {
        return $this->resolveSeparatedAttachments(
            $value,
            fn (string $path) => ! (MediaPath::isImage($path) || MediaPath::isVideo($path))
        );
    }

    public function getAttachmentsAttribute($value): array
    {
        $legacy = self::normalizeAttachmentPaths($value);
        $media = self::normalizeAttachmentPaths($this->attributes['media_attachments'] ?? null);
        $files = self::normalizeAttachmentPaths($this->attributes['file_attachments'] ?? null);

        if ($media !== [] || $files !== []) {
            return array_values(array_unique([
                ...$media,
                ...$files,
            ]));
        }

        return $legacy;
    }

    public function setAttachmentsAttribute($value): void
    {
        $attachments = self::normalizeAttachmentPaths($value);
        [$media, $files] = self::splitAttachments($attachments);

        $this->attributes['attachments'] = $attachments === [] ? null : json_encode($attachments);
        $this->attributes['media_attachments'] = $media === [] ? null : json_encode($media);
        $this->attributes['file_attachments'] = $files === [] ? null : json_encode($files);
    }

    public function setMediaAttachmentsAttribute($value): void
    {
        $media = self::normalizeAttachmentPaths($value);
        $files = $this->file_attachments;
        $attachments = array_values(array_unique([...$media, ...$files]));

        $this->attributes['media_attachments'] = $media === [] ? null : json_encode($media);
        $this->attributes['attachments'] = $attachments === [] ? null : json_encode($attachments);
    }

    public function setFileAttachmentsAttribute($value): void
    {
        $files = self::normalizeAttachmentPaths($value);
        $media = $this->media_attachments;
        $attachments = array_values(array_unique([...$media, ...$files]));

        $this->attributes['file_attachments'] = $files === [] ? null : json_encode($files);
        $this->attributes['attachments'] = $attachments === [] ? null : json_encode($attachments);
    }

    private function resolveSeparatedAttachments(mixed $value, callable $filter): array
    {
        $attachments = self::normalizeAttachmentPaths($value);

        if ($attachments !== []) {
            return $attachments;
        }

        return array_values(array_filter($this->attachments, $filter));
    }

    public static function normalizeAttachmentPaths(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            fn (mixed $attachment) => is_string($attachment) && filled($attachment)
        ));
    }

    public static function splitAttachments(array $attachments): array
    {
        $attachments = self::normalizeAttachmentPaths($attachments);

        $media = array_values(array_filter(
            $attachments,
            fn (string $path) => MediaPath::isImage($path) || MediaPath::isVideo($path)
        ));

        $files = array_values(array_filter(
            $attachments,
            fn (string $path) => ! in_array($path, $media, true)
        ));

        return [$media, $files];
    }
}
