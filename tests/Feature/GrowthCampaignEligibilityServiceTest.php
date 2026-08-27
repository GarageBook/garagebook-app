<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use App\Services\Growth\GrowthCampaignEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthCampaignEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_gmail_without_organization_override_is_blocked(): void
    {
        [$campaign, $prospect] = $this->eligibleProspect('club@gmail.com');

        $this->assertSame(
            GrowthCampaignEligibilityService::REASON_PERSONAL_EMAIL,
            $this->eligibility()->firstBlockingReason($prospect, $campaign),
        );
    }

    public function test_outlook_without_organization_override_is_blocked(): void
    {
        [$campaign, $prospect] = $this->eligibleProspect('club@outlook.com');

        $this->assertSame(
            GrowthCampaignEligibilityService::REASON_PERSONAL_EMAIL,
            $this->eligibility()->firstBlockingReason($prospect, $campaign),
        );
    }

    public function test_gmail_with_organization_override_is_eligible(): void
    {
        [$campaign, $prospect] = $this->eligibleProspect('club@gmail.com', true);

        $this->assertNull($this->eligibility()->firstBlockingReason($prospect, $campaign));
    }

    public function test_outlook_with_organization_override_is_eligible(): void
    {
        [$campaign, $prospect] = $this->eligibleProspect('club@outlook.com', true);

        $this->assertNull($this->eligibility()->firstBlockingReason($prospect, $campaign));
    }

    public function test_organization_override_does_not_bypass_duplicate_protection(): void
    {
        [$campaign, $original] = $this->eligibleProspect('club@gmail.com', true);
        [, $duplicate] = $this->eligibleProspect('ander@gmail.com', true, [
            'duplicate_of_id' => $original->id,
        ], $campaign);

        $this->assertSame(
            GrowthCampaignEligibilityService::REASON_DUPLICATE,
            $this->eligibility()->firstBlockingReason($duplicate, $campaign),
        );
    }

    public function test_organization_override_does_not_bypass_already_sent_protection(): void
    {
        [$campaign, $prospect] = $this->eligibleProspect('club@gmail.com', true);
        GrowthOutreachEvent::query()->create([
            'growth_prospect_id' => $prospect->id,
            'campaign_id' => $campaign->id,
            'campaign_slug' => $campaign->slug,
            'event_type' => GrowthOutreachEvent::TYPE_SENT,
            'occurred_at' => now(),
        ]);

        $this->assertSame(
            GrowthCampaignEligibilityService::REASON_ALREADY_RECEIVED_CAMPAIGN,
            $this->eligibility()->firstBlockingReason($prospect->fresh(), $campaign),
        );
    }

    public function test_business_email_remains_eligible_without_override(): void
    {
        [$campaign, $prospect] = $this->eligibleProspect('info@motorclub.example');

        $this->assertNull($this->eligibility()->firstBlockingReason($prospect, $campaign));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{GrowthCampaign, GrowthProspect}
     */
    private function eligibleProspect(
        string $email,
        bool $verifiedAsOrganization = false,
        array $overrides = [],
        ?GrowthCampaign $campaign = null,
    ): array {
        $campaign ??= GrowthCampaign::factory()->create([
            'name' => 'Club2026',
            'slug' => 'club2026',
        ]);

        $prospect = GrowthProspect::factory()->create(array_merge([
            'campaign_id' => $campaign->id,
            'email' => $email,
            'normalized_email' => $email,
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'verification_required' => false,
            'email_verified_as_organization' => $verifiedAsOrganization,
            'status' => GrowthProspect::LIFECYCLE_READY,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'skip_reason' => null,
            'last_contacted_at' => null,
        ], $overrides));

        return [$campaign, $prospect];
    }

    private function eligibility(): GrowthCampaignEligibilityService
    {
        return app(GrowthCampaignEligibilityService::class);
    }
}
