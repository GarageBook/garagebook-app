<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;

class GrowthOutreachEventLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(GrowthProspect $prospect, string $eventType, ?GrowthCampaign $campaign = null, ?string $campaignSlug = null, ?string $reason = null, ?array $metadata = null): GrowthOutreachEvent
    {
        return GrowthOutreachEvent::query()->create([
            'growth_prospect_id' => $prospect->id,
            'campaign_id' => $campaign?->id,
            'campaign_slug' => $campaign?->slug ?: $campaignSlug,
            'event_type' => $eventType,
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
