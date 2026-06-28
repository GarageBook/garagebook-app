<?php

namespace App\Services\Growth;

use App\Models\GrowthProspect;

class GrowthProspectTrackingUrlGenerator
{
    public function generate(GrowthProspect $prospect): ?string
    {
        $partnerSlug = $prospect->partner_slug;
        $campaignSlug = $prospect->campaign?->slug;

        if (blank($partnerSlug) || blank($campaignSlug)) {
            return null;
        }

        return url('/start?'.http_build_query([
            'utm_source' => $partnerSlug,
            'utm_medium' => 'partner',
            'utm_campaign' => $campaignSlug,
            'partner_slug' => $partnerSlug,
            'campaign_slug' => $campaignSlug,
        ]));
    }
}
