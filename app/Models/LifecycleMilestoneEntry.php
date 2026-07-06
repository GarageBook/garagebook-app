<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifecycleMilestoneEntry extends Model
{
    protected $fillable = [
        'user_id',
        'milestone',
        'achieved_at',
        'metadata',
    ];

    protected $casts = [
        'achieved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
