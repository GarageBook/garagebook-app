<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use Illuminate\Support\Carbon;

class GrowthCampaignQueueService
{
    public function __construct(
        private readonly GrowthCampaignEligibilityService $eligibility,
        private readonly GrowthOutreachEventLogger $events,
    ) {}

    /**
     * @return array<string, int>
     */
    public function queue(string $campaignSlug, ?int $limit = null): array
    {
        $result = $this->process($campaignSlug, $limit, false);

        return $result['summary'];
    }

    /**
     * @return array{summary:array<string, int>, selected: array<int, array{name:string,website:?string,email:?string,subtype:?string,quality_score:?int,last_contacted_at:?string}>}
     */
    public function preview(string $campaignSlug, ?int $limit = null): array
    {
        return $this->process($campaignSlug, $limit, true);
    }

    /**
     * @return array{summary:array<string, int>, selected: array<int, array{name:string,website:?string,email:?string,subtype:?string,quality_score:?int,last_contacted_at:?string}>}
     */
    private function process(string $campaignSlug, ?int $limit, bool $dryRun): array
    {
        $campaign = GrowthCampaign::query()->where('slug', $campaignSlug)->firstOrFail();
        $summary = [
            'total considered' => 0,
            'queued' => 0,
            'skipped already contacted' => 0,
            'skipped already received campaign' => 0,
            'skipped duplicate' => 0,
            'skipped invalid email' => 0,
            'skipped missing email' => 0,
            'skipped archived' => 0,
            'skipped manual review required' => 0,
            'skipped no website' => 0,
            'skipped personal email' => 0,
            'skipped suggested email' => 0,
        ];
        $selected = [];
        $processed = 0;

        GrowthProspect::query()
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
            ->where(function ($query) use ($campaign): void {
                $query->where('campaign_id', $campaign->id)
                    ->orWhere('last_campaign_slug', $campaign->slug)
                    ->orWhereNull('campaign_id');
            })
            ->orderBy('id')
            ->chunkById(100, function ($prospects) use ($campaign, $dryRun, $limit, &$processed, &$selected, &$summary): bool {
                foreach ($prospects as $prospect) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }

                    $processed++;
                    $summary['total considered']++;

                    $reason = $this->blockingReason($prospect, $campaign);

                    if ($reason !== null) {
                        $this->markSkipped($prospect, $campaign, $reason, $dryRun, $summary);

                        continue;
                    }

                    $summary['queued']++;

                    if ($dryRun) {
                        $selected[] = $this->selectionRecord($prospect);

                        continue;
                    }

                    $prospect->forceFill([
                        'campaign_id' => $campaign->id,
                        'last_campaign_id' => $campaign->id,
                        'last_campaign_slug' => $campaign->slug,
                        'skip_reason' => null,
                    ])->save();

                    $this->events->log($prospect, GrowthOutreachEvent::TYPE_QUEUED, $campaign);
                }

                return true;
            });

        return [
            'summary' => $summary,
            'selected' => $selected,
        ];
    }

    private function blockingReason(GrowthProspect $prospect, GrowthCampaign $campaign): ?string
    {
        if (filled($prospect->suggested_email)) {
            return 'suggested_email';
        }

        return $this->eligibility->firstBlockingReason($prospect, $campaign);
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function markSkipped(GrowthProspect $prospect, GrowthCampaign $campaign, string $reason, bool $dryRun, array &$summary): void
    {
        if (! $dryRun) {
            $prospect->forceFill(['skip_reason' => $reason])->save();
            $this->events->log($prospect, GrowthOutreachEvent::TYPE_SKIPPED, $campaign, null, $reason);
        }

        $key = match ($reason) {
            GrowthCampaignEligibilityService::REASON_ALREADY_CONTACTED_RECENTLY => 'skipped already contacted',
            GrowthCampaignEligibilityService::REASON_ALREADY_RECEIVED_CAMPAIGN => 'skipped already received campaign',
            GrowthCampaignEligibilityService::REASON_MISSING_EMAIL => 'skipped missing email',
            GrowthCampaignEligibilityService::REASON_INVALID_EMAIL => 'skipped invalid email',
            GrowthCampaignEligibilityService::REASON_DUPLICATE => 'skipped duplicate',
            GrowthCampaignEligibilityService::REASON_ARCHIVED => 'skipped archived',
            GrowthCampaignEligibilityService::REASON_NO_WEBSITE => 'skipped no website',
            GrowthCampaignEligibilityService::REASON_PERSONAL_EMAIL => 'skipped personal email',
            'suggested_email' => 'skipped suggested email',
            default => 'skipped manual review required',
        };

        $summary[$key]++;
    }

    /**
     * @return array{name:string,website:?string,email:?string,subtype:?string,quality_score:?int,last_contacted_at:?string}
     */
    private function selectionRecord(GrowthProspect $prospect): array
    {
        return [
            'name' => $prospect->name,
            'website' => $prospect->website,
            'email' => $prospect->email,
            'subtype' => $prospect->prospect_subtype,
            'quality_score' => $prospect->quality_score,
            'last_contacted_at' => $prospect->last_contacted_at instanceof Carbon
                ? $prospect->last_contacted_at->toDateTimeString()
                : null,
        ];
    }
}
