<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifecycleRuleEvaluation extends Model
{
    protected $fillable = [
        'user_id',
        'rule_name',
        'matched',
        'reason',
        'evaluated_at',
        'cooldown_until',
        'metadata',
    ];

    protected $casts = [
        'cooldown_until' => 'datetime',
        'evaluated_at' => 'datetime',
        'matched' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
