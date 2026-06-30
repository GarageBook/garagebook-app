<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;

class GrowthCampaignQueueService
{
    public function __construct(
        private readonly GrowthCampaignEligibilityService $eligibility,
        private readonly GrowthOutreachEventLogger $events,
    ) {}

    /**
     * @return array<string, int>
     */
    public function queue(string $campaignSlug): array
    {
        $campaign = GrowthCampaign::query()->where('slug', $campaignSlug)->firstOrFail();
        $summary = [
            'total considered' => 0,
            'queued' => 0,
            'skipped already contacted' => 0,
            'skipped missing email' => 0,
            'skipped duplicate' => 0,
            'skipped archived' => 0,
            'skipped already received campaign' => 0,
            'skipped invalid email' => 0,
            'skipped manual review required' => 0,
            'skipped no website' => 0,
        ];

        GrowthProspect::query()
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
            ->where(function ($query) use ($campaign): void {
                $query->where('campaign_id', $campaign->id)
                    ->orWhere('last_campaign_slug', $campaign->slug)
                    ->orWhereNull('campaign_id');
            })
            ->orderBy('id')
            ->chunkById(100, function ($prospects) use ($campaign, &$summary): void {
                foreach ($prospects as $prospect) {
                    $summary['total considered']++;
                    $reason = $this->eligibility->firstBlockingReason($prospect, $campaign);

                    if ($reason !== null) {
                        $this->markSkipped($prospect, $campaign, $reason, $summary);

                        continue;
                    }

                    $prospect->forceFill([
                        'campaign_id' => $campaign->id,
                        'last_campaign_id' => $campaign->id,
                        'last_campaign_slug' => $campaign->slug,
                        'skip_reason' => null,
                    ])->save();

                    $this->events->log($prospect, GrowthOutreachEvent::TYPE_QUEUED, $campaign);
                    $summary['queued']++;
                }
            });

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function markSkipped(GrowthProspect $prospect, GrowthCampaign $campaign, string $reason, array &$summary): void
    {
        $prospect->forceFill(['skip_reason' => $reason])->save();
        $this->events->log($prospect, GrowthOutreachEvent::TYPE_SKIPPED, $campaign, null, $reason);

        $key = match ($reason) {
            GrowthCampaignEligibilityService::REASON_ALREADY_CONTACTED_RECENTLY => 'skipped already contacted',
            GrowthCampaignEligibilityService::REASON_ALREADY_RECEIVED_CAMPAIGN => 'skipped already received campaign',
            GrowthCampaignEligibilityService::REASON_MISSING_EMAIL => 'skipped missing email',
            GrowthCampaignEligibilityService::REASON_INVALID_EMAIL => 'skipped invalid email',
            GrowthCampaignEligibilityService::REASON_DUPLICATE => 'skipped duplicate',
            GrowthCampaignEligibilityService::REASON_ARCHIVED => 'skipped archived',
            GrowthCampaignEligibilityService::REASON_NO_WEBSITE => 'skipped no website',
            default => 'skipped manual review required',
        };

        $summary[$key]++;
    }
}
