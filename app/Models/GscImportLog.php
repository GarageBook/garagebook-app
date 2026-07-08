<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscImportLog extends Model
{
    protected $fillable = [
        'date',
        'pages_imported',
        'queries_imported',
        'user_id',
        'duration_ms',
        'status',
        'warnings',
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
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
