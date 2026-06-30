<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Community2026CleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_corrects_existing_community_records(): void
    {
        $campaign = GrowthCampaign::query()->create([
            'name' => 'Community2026',
            'slug' => 'community2026',
            'description' => 'Community test',
            'status' => GrowthCampaign::STATUS_DRAFT,
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Ready Club',
            'website' => 'https://ready.example',
            'normalized_domain' => 'ready.example',
            'email' => 'info@ready.example',
            'normalized_email' => 'info@ready.example',
            'source_url' => 'https://source.example/ready',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Missing Club',
            'website' => 'https://missing.example',
            'normalized_domain' => 'missing.example',
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Invalid Club',
            'website' => 'https://invalid.example',
            'normalized_domain' => 'invalid.example',
            'email' => 'invalid-email',
            'normalized_email' => 'invalid-email',
            'email_status' => GrowthProspect::EMAIL_STATUS_INVALID,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        $master = GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Master Club',
            'website' => 'https://master.example',
            'normalized_domain' => 'master.example',
            'email' => 'info@master.example',
            'normalized_email' => 'info@master.example',
            'source_url' => 'https://source.example/master',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Duplicate Club',
            'website' => 'https://duplicate.example',
            'normalized_domain' => 'duplicate.example',
            'email' => 'dup@duplicate.example',
            'normalized_email' => 'dup@duplicate.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'verification_required' => false,
            'duplicate_of_id' => $master->id,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        $this->artisan('garagebook:community2026-cleanup')
            ->expectsOutput('Community2026 cleanup voltooid.')
            ->assertSuccessful();

        $this->assertSame(2, GrowthProspect::query()->where('campaign_id', $campaign->id)->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)->count());
        $this->assertSame(1, GrowthProspect::query()->where('campaign_id', $campaign->id)->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)->count());
        $this->assertSame(1, GrowthProspect::query()->where('campaign_id', $campaign->id)->where('email_status', GrowthProspect::EMAIL_STATUS_INVALID)->count());
        $this->assertSame(2, GrowthProspect::query()->where('campaign_id', $campaign->id)->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)->count());
        $this->assertSame(1, GrowthProspect::query()->where('campaign_id', $campaign->id)->whereNotNull('duplicate_of_id')->count());

        $this->assertSame(GrowthProspect::LIFECYCLE_READY, $master->fresh()->lifecycle_status);
        $this->assertSame(GrowthProspect::LIFECYCLE_ENRICHED, GrowthProspect::query()->where('name', 'Missing Club')->firstOrFail()->lifecycle_status);
        $this->assertSame(GrowthProspect::LIFECYCLE_MANUAL_REVIEW, GrowthProspect::query()->where('name', 'Invalid Club')->firstOrFail()->lifecycle_status);
        $this->assertSame(GrowthProspect::LIFECYCLE_MANUAL_REVIEW, GrowthProspect::query()->where('name', 'Duplicate Club')->firstOrFail()->lifecycle_status);
    }
}
