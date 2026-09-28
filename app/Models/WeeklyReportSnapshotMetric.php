<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReportSnapshotMetric extends Model
{
    protected $fillable = [
        'snapshot_id', 'metric_key', 'value_numeric', 'numerator', 'denominator',
        'unit', 'definition_version', 'raw_label',
    ];

    protected function casts(): array
    {
        return ['value_numeric' => 'decimal:4', 'numerator' => 'integer', 'denominator' => 'integer'];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(WeeklyReportSnapshot::class, 'snapshot_id');
    }
}
