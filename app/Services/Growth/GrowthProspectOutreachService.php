<?php

namespace App\Services\Growth;

use App\Mail\GrowthProspectOutreachMail;
use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GrowthProspectOutreachService
{
    public function __construct(
        private readonly GrowthProspectTrackingUrlGenerator $trackingUrlGenerator,
        private readonly GrowthCampaignEligibilityService $eligibility,
        private readonly GrowthOutreachEventLogger $events,
    ) {}

    /**
     * @param  EloquentCollection<int, GrowthProspect>  $prospects
     * @return array{sent:int, skipped:int}
     */
    public function sendClub2026Bulk(EloquentCollection $prospects): array
    {
        return $this->sendCampaignBulk($prospects, 'club2026');
    }

    /**
     * @param  EloquentCollection<int, GrowthProspect>  $prospects
     * @return array{sent:int, skipped:int}
     */
    public function sendCampaignBulk(EloquentCollection $prospects, string $campaignSlug): array
    {
        $result = ['sent' => 0, 'skipped' => 0];

        foreach ($prospects as $prospect) {
            if ($this->sendCampaign($prospect, $campaignSlug)) {
                $result['sent']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }

    public function sendClub2026(GrowthProspect $prospect): bool
    {
        return $this->sendCampaign($prospect, 'club2026');
    }

    public function sendCampaign(GrowthProspect $prospect, string $campaignSlug): bool
    {
        $campaign = $this->campaignFor($prospect, $campaignSlug);

        if (! $campaign instanceof GrowthCampaign) {
            $this->markSkipped($prospect, null, $campaignSlug, GrowthCampaignEligibilityService::REASON_MANUAL_REVIEW_REQUIRED);

            return false;
        }

        $this->normalizeProspectForSend($prospect);
        $reason = $this->eligibility->firstBlockingReason($prospect->fresh(), $campaign);

        if ($reason !== null) {
            $this->markSkipped($prospect, $campaign, $campaign->slug, $reason);

            return false;
        }

        $trackingUrl = $this->trackingUrlFor($prospect, $campaign);

        if ($trackingUrl === null) {
            $this->markSkipped($prospect, $campaign, $campaign->slug, GrowthCampaignEligibilityService::REASON_MANUAL_REVIEW_REQUIRED);

            return false;
        }

        $email = trim((string) $prospect->email);

        try {
            Mail::to($email)->send(new GrowthProspectOutreachMail(
                $this->recipientNameFor($prospect),
                $trackingUrl,
            ));
        } catch (Throwable $exception) {
            Log::warning('growth_prospect_outreach_send_failed', [
                'growth_prospect_id' => $prospect->id,
                'campaign_slug' => $campaign->slug,
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            $this->events->log($prospect, GrowthOutreachEvent::TYPE_FAILED, $campaign, null, $exception->getMessage());

            return false;
        }

        $contactedAt = now();

        $prospect->forceFill([
            'status' => GrowthProspect::LIFECYCLE_CONTACTED,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_CONTACTED,
            'last_contacted_at' => $contactedAt,
            'next_follow_up_at' => $contactedAt->copy()->addDays(7),
            'last_campaign_id' => $campaign->id,
            'last_campaign_slug' => $campaign->slug,
            'campaign_id' => $campaign->id,
            'skip_reason' => null,
        ])->save();

        $this->events->log($prospect, GrowthOutreachEvent::TYPE_SENT, $campaign);

        return true;
    }

    private function campaignFor(GrowthProspect $prospect, string $campaignSlug): ?GrowthCampaign
    {
        $prospect->loadMissing('campaign');

        if ($prospect->campaign?->slug === $campaignSlug) {
            return $prospect->campaign;
        }

        $campaign = GrowthCampaign::query()->where('slug', $campaignSlug)->first();

        if ($campaign) {
            $prospect->setRelation('campaign', $campaign);
        }

        return $campaign;
    }

    private function trackingUrlFor(GrowthProspect $prospect, GrowthCampaign $campaign): ?string
    {
        $prospect->setRelation('campaign', $campaign);

        return $this->trackingUrlGenerator->generate($prospect);
    }

    private function normalizeProspectForSend(GrowthProspect $prospect): void
    {
        $normalizer = app(GrowthProspectNormalizer::class);
        $normalizedEmail = $normalizer->normalizeEmail($prospect->email);
        $normalizedDomain = $prospect->normalized_domain ?: $normalizer->normalizeDomain($prospect->website);
        $emailStatus = $prospect->email_status;

        if (blank($emailStatus) || ($emailStatus === GrowthProspect::EMAIL_STATUS_MISSING && $normalizedEmail !== null)) {
            $emailStatus = $normalizedEmail === null ? GrowthProspect::EMAIL_STATUS_MISSING : GrowthProspect::EMAIL_STATUS_FOUND;
        }

        if ($normalizedEmail !== null && filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            $emailStatus = GrowthProspect::EMAIL_STATUS_INVALID;
        }

        $prospect->forceFill([
            'normalized_email' => $normalizedEmail,
            'normalized_domain' => $normalizedDomain,
            'organization_key' => $prospect->organization_key ?: $normalizer->organizationKey($prospect->name, $normalizedDomain),
            'email_status' => $emailStatus,
            'phone' => $normalizer->normalizePhone($prospect->phone),
        ])->save();
    }

    private function markSkipped(GrowthProspect $prospect, ?GrowthCampaign $campaign, ?string $campaignSlug, string $reason): void
    {
        $prospect->forceFill(['skip_reason' => $reason])->save();
        $this->events->log($prospect, GrowthOutreachEvent::TYPE_SKIPPED, $campaign, $campaignSlug, $reason);
    }

    private function recipientNameFor(GrowthProspect $prospect): string
    {
        return trim((string) ($prospect->contact_name ?: $prospect->name));
    }
}
