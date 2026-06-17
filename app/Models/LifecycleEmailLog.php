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

    protected $fillable = [
        'user_id',
        'email_key',
        'subject',
        'status',
        'sent_at',
        'failed_at',
        'skipped_at',
        'error_message',
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
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'documents_count' => 'integer',
        'failed_at' => 'datetime',
        'goal_completed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'maintenance_logs_count' => 'integer',
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
