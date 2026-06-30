<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GrowthCampaignEligibilityService
{
    public const REASON_ALREADY_CONTACTED_RECENTLY = 'already_contacted_recently';

    public const REASON_ALREADY_RECEIVED_CAMPAIGN = 'already_received_campaign';

    public const REASON_MISSING_EMAIL = 'missing_email';

    public const REASON_INVALID_EMAIL = 'invalid_email';

    public const REASON_DUPLICATE = 'duplicate';

    public const REASON_ARCHIVED = 'archived';

    public const REASON_NO_WEBSITE = 'no_website';

    public const REASON_MANUAL_REVIEW_REQUIRED = 'manual_review_required';

    public function __construct(
        private readonly GrowthProspectNormalizer $normalizer,
    ) {}

    public function firstBlockingReason(GrowthProspect $prospect, GrowthCampaign $campaign): ?string
    {
        $prospect->loadMissing('outreachEvents');

        if ($prospect->lifecycle_status === GrowthProspect::LIFECYCLE_ARCHIVED || $prospect->status === 'archived') {
            return self::REASON_ARCHIVED;
        }

        if ($prospect->duplicate_of_id !== null) {
            return self::REASON_DUPLICATE;
        }

        if ($this->alreadyReceivedCampaign($prospect, $campaign->slug)) {
            return self::REASON_ALREADY_RECEIVED_CAMPAIGN;
        }

        if ($this->recentlyContactedSimilarProspect($prospect)) {
            return self::REASON_ALREADY_CONTACTED_RECENTLY;
        }

        if (blank($prospect->website) && blank($prospect->normalized_domain)) {
            return self::REASON_NO_WEBSITE;
        }

        if ($prospect->email_status === GrowthProspect::EMAIL_STATUS_MISSING || blank($prospect->email)) {
            return self::REASON_MISSING_EMAIL;
        }

        if ($prospect->email_status === GrowthProspect::EMAIL_STATUS_INVALID || filter_var((string) $prospect->email, FILTER_VALIDATE_EMAIL) === false) {
            return self::REASON_INVALID_EMAIL;
        }

        if ($prospect->verification_required) {
            return self::REASON_MANUAL_REVIEW_REQUIRED;
        }

        if ($this->hasSimilarDuplicate($prospect)) {
            return self::REASON_DUPLICATE;
        }

        return null;
    }

    public function alreadyReceivedCampaign(GrowthProspect $prospect, string $campaignSlug): bool
    {
        return $this->sentEventsForSimilarProspects($prospect)
            ->where('campaign_slug', $campaignSlug)
            ->exists();
    }

    public function recentlyContactedSimilarProspect(GrowthProspect $prospect, ?Carbon $since = null): bool
    {
        $since ??= now()->subDays(90);

        return $this->sentEventsForSimilarProspects($prospect)
            ->where('occurred_at', '>=', $since)
            ->exists();
    }

    private function hasSimilarDuplicate(GrowthProspect $prospect): bool
    {
        return $this->similarProspectsQuery($prospect)
            ->whereKeyNot($prospect->id)
            ->exists();
    }

    private function sentEventsForSimilarProspects(GrowthProspect $prospect): Builder
    {
        $normalizedEmail = $prospect->normalized_email ?: $this->normalizer->normalizeEmail($prospect->email);
        $normalizedDomain = $prospect->normalized_domain ?: $this->normalizer->normalizeDomain($prospect->website);
        $organizationKey = $prospect->organization_key ?: $this->normalizer->organizationKey($prospect->name, $normalizedDomain);
        $phone = $this->normalizer->normalizePhone($prospect->phone);

        return GrowthOutreachEvent::query()
            ->where('event_type', GrowthOutreachEvent::TYPE_SENT)
            ->whereHas('prospect', function (Builder $query) use ($prospect, $normalizedEmail, $normalizedDomain, $organizationKey, $phone): void {
                $query->whereKey($prospect->id);

                if (filled($normalizedEmail)) {
                    $query->orWhere('normalized_email', $normalizedEmail)
                        ->orWhere('email', $normalizedEmail);
                }

                if (filled($normalizedDomain)) {
                    $query->orWhere('normalized_domain', $normalizedDomain)
                        ->orWhere('website', 'like', '%'.$normalizedDomain.'%');
                }

                if (filled($organizationKey)) {
                    $query->orWhere('organization_key', $organizationKey);
                }

                if (filled($phone)) {
                    $query->orWhere('phone', $phone);
                }

                if ($prospect->duplicate_of_id !== null) {
                    $query->orWhereKey($prospect->duplicate_of_id);
                }

                $query->orWhere('duplicate_of_id', $prospect->id);
            });
    }

    private function similarProspectsQuery(GrowthProspect $prospect): Builder
    {
        $normalizedEmail = $prospect->normalized_email ?: $this->normalizer->normalizeEmail($prospect->email);
        $normalizedDomain = $prospect->normalized_domain ?: $this->normalizer->normalizeDomain($prospect->website);
        $organizationKey = $prospect->organization_key ?: $this->normalizer->organizationKey($prospect->name, $normalizedDomain);
        $phone = $this->normalizer->normalizePhone($prospect->phone);

        return GrowthProspect::query()
            ->where(function (Builder $query) use ($prospect, $normalizedEmail, $normalizedDomain, $organizationKey, $phone): void {
                if (filled($normalizedEmail)) {
                    $query->orWhere('normalized_email', $normalizedEmail)
                        ->orWhere('email', $normalizedEmail);
                }

                if (filled($normalizedDomain)) {
                    $query->orWhere('normalized_domain', $normalizedDomain)
                        ->orWhere('website', 'like', '%'.$normalizedDomain.'%');
                }

                if (filled($organizationKey)) {
                    $query->orWhere('organization_key', $organizationKey);
                }

                if (filled($phone)) {
                    $query->orWhere('phone', $phone);
                }

                if ($prospect->duplicate_of_id !== null) {
                    $query->orWhereKey($prospect->duplicate_of_id);
                }

                $query->orWhere('duplicate_of_id', $prospect->id);
            });
    }
}
