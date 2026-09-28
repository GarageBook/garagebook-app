<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyReportSnapshot extends Model
{
    protected $fillable = [
        'report_date', 'source_type', 'definition_version', 'raw_text', 'raw_text_hash',
        'parser_version', 'imported_at', 'imported_by_user_id', 'parse_status', 'warnings',
    ];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'imported_at' => 'datetime', 'warnings' => 'array'];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(WeeklyReportSnapshotMetric::class, 'snapshot_id');
    }
}
