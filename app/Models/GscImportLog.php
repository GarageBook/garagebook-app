<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscImportLog extends Model
{
    protected $fillable = [
        'gsc_import_session_id',
        'date',
        'pages_imported',
        'queries_imported',
        'user_id',
        'duration_ms',
        'status',
        'warnings',
        'notices',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'pages_imported' => 'integer',
            'queries_imported' => 'integer',
            'duration_ms' => 'integer',
            'warnings' => 'array',
            'notices' => 'array',
            'errors' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GscImportSession::class, 'gsc_import_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
