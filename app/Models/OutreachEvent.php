<?php

namespace App\Models;

use Database\Factories\OutreachEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachEvent extends Model
{
    /** @use HasFactory<OutreachEventFactory> */
    use HasFactory;

    protected $fillable = [
        'outreach_prospect_id',
        'event_type',
        'ip_address',
        'user_agent',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(OutreachProspect::class, 'outreach_prospect_id');
    }
}
