<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class LifecycleEmailLog extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'email_key',
        'subject',
        'status',
        'sent_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
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
