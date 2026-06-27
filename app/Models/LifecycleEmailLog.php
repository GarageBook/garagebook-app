<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class LifecycleEmailLog extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const TRIGGER_NO_VEHICLE_DAY2 = 'no_vehicle_day2';

    protected $fillable = [
        'user_id',
        'email_key',
        'trigger',
        'subject',
        'mail_class',
        'status',
        'queued_at',
        'sent_at',
        'failed_at',
        'skipped_at',
        'error_message',
        'error',
        'reason_skipped',
        'vehicles_count',
        'maintenance_logs_count',
        'documents_count',
        'last_login_at',
        'clicked_at',
        'goal_completed_at',
        'retried_at',
        'retry_status',
        'retry_log_id',
        'retry_error_message',
        'mailer',
        'mail_transport',
        'release_path',
        'queue_job_id',
        'retry_of_log_id',
        'resend_message_id',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'documents_count' => 'integer',
        'failed_at' => 'datetime',
        'goal_completed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'maintenance_logs_count' => 'integer',
        'queued_at' => 'datetime',
        'retried_at' => 'datetime',
        'sent_at' => 'datetime',
        'skipped_at' => 'datetime',
        'vehicles_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Onbekende gebruiker',
            'email' => '-',
        ]);
    }

    public function retryLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_log_id');
    }

    public function retryOfLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_log_id');
    }

    public static function existingColumnAttributes(array $attributes): array
    {
        if (! SchemaFacade::hasTable('lifecycle_email_logs')) {
            return [];
        }

        return collect($attributes)
            ->filter(fn (mixed $value, string $key): bool => SchemaFacade::hasColumn('lifecycle_email_logs', $key))
            ->all();
    }

    public function deliveryResolutionStatus(): string
    {
        if ($this->status !== self::STATUS_FAILED) {
            return $this->status;
        }

        return $this->isResolvedFailure() ? 'resolved' : 'unresolved';
    }

    public function isResolvedFailure(): bool
    {
        if ($this->status !== self::STATUS_FAILED) {
            return false;
        }

        if ($this->retry_status === self::STATUS_SENT) {
            return true;
        }

        if ($this->retry_log_id && self::query()
            ->whereKey($this->retry_log_id)
            ->where('status', self::STATUS_SENT)
            ->exists()) {
            return true;
        }

        $query = self::query()
            ->where('user_id', $this->user_id)
            ->where('status', self::STATUS_SENT)
            ->whereKeyNot($this->getKey())
            ->where('created_at', '>=', $this->created_at);

        if (SchemaFacade::hasColumn('lifecycle_email_logs', 'retry_of_log_id')) {
            $query->where(function ($query): void {
                $query
                    ->where('email_key', $this->email_key)
                    ->orWhere('retry_of_log_id', $this->getKey());
            });
        } else {
            $query->where('email_key', $this->email_key);
        }

        return $query->exists();
    }

    public function userDisplayName(): string
    {
        if (! SchemaFacade::hasTable('users')) {
            return 'Onbekende gebruiker';
        }

        return (string) ($this->user?->name ?: 'Onbekende gebruiker');
    }

    public function userDisplayEmail(): string
    {
        if (! SchemaFacade::hasTable('users')) {
            return '-';
        }

        return (string) ($this->user?->email ?: '-');
    }
}
