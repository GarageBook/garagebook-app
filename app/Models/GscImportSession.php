<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GscImportSession extends Model
{
    protected $fillable = [
        'import_date',
        'user_id',
        'status',
        'total_files',
        'processed_files',
        'skipped_files',
        'pages_imported',
        'queries_imported',
        'countries_imported',
        'devices_imported',
        'search_appearances_imported',
        'date_rows_imported',
        'duration_ms',
        'warnings',
        'notices',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'import_date' => 'date',
            'total_files' => 'integer',
            'processed_files' => 'integer',
            'skipped_files' => 'integer',
            'pages_imported' => 'integer',
            'queries_imported' => 'integer',
            'countries_imported' => 'integer',
            'devices_imported' => 'integer',
            'search_appearances_imported' => 'integer',
            'date_rows_imported' => 'integer',
            'duration_ms' => 'integer',
            'warnings' => 'array',
            'notices' => 'array',
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GscImportLog::class);
    }
}
