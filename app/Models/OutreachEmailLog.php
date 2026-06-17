<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachEmailLog extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'outreach_campaign_id',
        'outreach_prospect_id',
        'to_email',
        'subject',
        'body_snapshot',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'outreach_campaign_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(OutreachProspect::class, 'outreach_prospect_id');
    }
}
