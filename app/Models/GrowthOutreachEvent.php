<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthOutreachEvent extends Model
{
    public const TYPE_IMPORTED = 'imported';

    public const TYPE_ENRICHED = 'enriched';

    public const TYPE_QUEUED = 'queued';

    public const TYPE_SENT = 'sent';

    public const TYPE_SKIPPED = 'skipped';

    public const TYPE_OPENED = 'opened';

    public const TYPE_CLICKED = 'clicked';

    public const TYPE_REPLIED = 'replied';

    public const TYPE_FAILED = 'failed';

    protected $fillable = [
        'growth_prospect_id',
        'campaign_id',
        'campaign_slug',
        'event_type',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(GrowthProspect::class, 'growth_prospect_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GrowthCampaign::class, 'campaign_id');
    }
}
