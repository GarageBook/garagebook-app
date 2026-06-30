<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Community2026CleanupService
{
    public const CAMPAIGN_SLUG = 'community2026';

    public function __construct(
        private readonly GrowthProspectNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, int>
     */
    public function cleanup(): array
    {
        $summary = [
            'ready' => 0,
            'needs_email' => 0,
            'invalid_email' => 0,
            'manual_review' => 0,
            'duplicates' => 0,
            'updated' => 0,
        ];

        $this->prospectsQuery()->orderBy('id')->chunkById(100, function ($prospects) use (&$summary): void {
            foreach ($prospects as $prospect) {
                $changes = $this->normalizedState($prospect);
                $prospect->forceFill($changes)->save();
                $summary['updated']++;
            }
        });

        $summary['ready'] = $this->prospectsQuery()->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)->count();
        $summary['needs_email'] = $this->prospectsQuery()->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)->count();
        $summary['invalid_email'] = $this->prospectsQuery()->where('email_status', GrowthProspect::EMAIL_STATUS_INVALID)->count();
        $summary['manual_review'] = $this->prospectsQuery()
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)
            ->count();
        $summary['duplicates'] = $this->prospectsQuery()->whereNotNull('duplicate_of_id')->count();

        return $summary;
    }

    /**
     * @return Collection<int, GrowthProspect>
     */
    public function attentionRecords(int $limit = 20): Collection
    {
        return $this->prospectsQuery()
            ->where(function (Builder $query): void {
                $query->where('lifecycle_status', '!=', GrowthProspect::LIFECYCLE_READY)
                    ->orWhereNotNull('skip_reason')
                    ->orWhereNotNull('duplicate_of_id');
            })
            ->orderByRaw("CASE WHEN duplicate_of_id IS NOT NULL THEN 1 WHEN email_status = 'invalid' THEN 2 WHEN email_status = 'missing' THEN 3 WHEN lifecycle_status = 'manual_review' THEN 4 ELSE 5 END")
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedState(GrowthProspect $prospect): array
    {
        $normalizedEmail = $this->normalizer->normalizeEmail($prospect->email);
        $normalizedDomain = $prospect->normalized_domain ?: $this->normalizer->normalizeDomain($prospect->website);
        $organizationKey = $prospect->organization_key ?: $this->normalizer->organizationKey($prospect->name, $normalizedDomain);
        $phone = $this->normalizer->normalizePhone($prospect->phone);
        $emailStatus = $this->resolveEmailStatus($prospect, $normalizedEmail);
        $verificationRequired = $this->verificationRequired($prospect, $emailStatus);
        $lifecycleStatus = $this->resolveLifecycleStatus($prospect, $emailStatus, $verificationRequired);
        $skipReason = $this->resolveSkipReason($prospect, $emailStatus, $lifecycleStatus);

        return array_filter([
            'normalized_email' => $normalizedEmail,
            'normalized_domain' => $normalizedDomain,
            'organization_key' => $organizationKey,
            'phone' => $phone,
            'email_status' => $emailStatus,
            'verification_required' => $verificationRequired,
            'lifecycle_status' => $lifecycleStatus,
            'status' => $lifecycleStatus,
            'skip_reason' => $skipReason,
        ], static fn ($value): bool => $value !== null);
    }

    private function resolveEmailStatus(GrowthProspect $prospect, ?string $normalizedEmail): string
    {
        if ($normalizedEmail === null) {
            return GrowthProspect::EMAIL_STATUS_MISSING;
        }

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return GrowthProspect::EMAIL_STATUS_INVALID;
        }

        return $prospect->email_status === GrowthProspect::EMAIL_STATUS_VERIFIED
            ? GrowthProspect::EMAIL_STATUS_VERIFIED
            : GrowthProspect::EMAIL_STATUS_FOUND;
    }

    private function verificationRequired(GrowthProspect $prospect, string $emailStatus): bool
    {
        if (in_array($emailStatus, [GrowthProspect::EMAIL_STATUS_MISSING, GrowthProspect::EMAIL_STATUS_INVALID], true)) {
            return true;
        }

        return blank($prospect->website) || blank($prospect->source_url);
    }

    private function resolveLifecycleStatus(GrowthProspect $prospect, string $emailStatus, bool $verificationRequired): string
    {
        if ($prospect->status === GrowthProspect::LIFECYCLE_ARCHIVED || $prospect->lifecycle_status === GrowthProspect::LIFECYCLE_ARCHIVED) {
            return GrowthProspect::LIFECYCLE_ARCHIVED;
        }

        if ($prospect->duplicate_of_id !== null) {
            return GrowthProspect::LIFECYCLE_MANUAL_REVIEW;
        }

        if ($emailStatus === GrowthProspect::EMAIL_STATUS_MISSING) {
            return GrowthProspect::LIFECYCLE_ENRICHED;
        }

        if ($emailStatus === GrowthProspect::EMAIL_STATUS_INVALID) {
            return GrowthProspect::LIFECYCLE_MANUAL_REVIEW;
        }

        return $verificationRequired ? GrowthProspect::LIFECYCLE_ENRICHED : GrowthProspect::LIFECYCLE_READY;
    }

    private function resolveSkipReason(GrowthProspect $prospect, string $emailStatus, string $lifecycleStatus): ?string
    {
        if ($prospect->status === GrowthProspect::LIFECYCLE_ARCHIVED || $prospect->lifecycle_status === GrowthProspect::LIFECYCLE_ARCHIVED) {
            return 'archived';
        }

        if ($prospect->duplicate_of_id !== null) {
            return 'duplicate';
        }

        return match ([$emailStatus, $lifecycleStatus]) {
            [GrowthProspect::EMAIL_STATUS_MISSING, GrowthProspect::LIFECYCLE_ENRICHED] => 'missing_email',
            [GrowthProspect::EMAIL_STATUS_INVALID, GrowthProspect::LIFECYCLE_MANUAL_REVIEW] => 'invalid_email',
            [GrowthProspect::EMAIL_STATUS_FOUND, GrowthProspect::LIFECYCLE_ENRICHED], [GrowthProspect::EMAIL_STATUS_VERIFIED, GrowthProspect::LIFECYCLE_ENRICHED] => 'manual_review_required',
            default => $lifecycleStatus === GrowthProspect::LIFECYCLE_MANUAL_REVIEW ? 'manual_review_required' : null,
        };
    }

    private function prospectsQuery(): Builder
    {
        $campaignId = $this->campaign()->id;

        return GrowthProspect::query()
            ->where(function (Builder $query) use ($campaignId): void {
                $query->where('campaign_id', $campaignId)
                    ->orWhere('last_campaign_slug', self::CAMPAIGN_SLUG);
            });
    }

    private function campaign(): GrowthCampaign
    {
        return GrowthCampaign::query()->updateOrCreate(
            ['slug' => self::CAMPAIGN_SLUG],
            [
                'name' => 'Community2026',
                'description' => 'Merkclubs, oldtimerclubs, camperclubs, youngtimerclubs en andere voertuigcommunities.',
                'status' => GrowthCampaign::STATUS_DRAFT,
            ],
        );
    }
}
