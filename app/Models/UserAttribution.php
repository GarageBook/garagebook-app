<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAttribution extends Model
{
    protected $fillable = [
        'user_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'landing_page',
        'referrer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
