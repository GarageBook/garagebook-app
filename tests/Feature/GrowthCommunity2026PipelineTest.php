<?php

namespace Tests\Feature;

use App\Mail\GrowthProspectOutreachMail;
use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use App\Services\Growth\Community2026ImportService;
use App\Services\Growth\GrowthCampaignEligibilityService;
use App\Services\Growth\GrowthProspectOutreachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GrowthCommunity2026PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_new_prospect(): void
    {
        $path = $this->writeImportCsv('community-new.csv', [
            ['Motorclub Noord', 'https://motorclub-noord.example', 'info@motorclub-noord.example', '0612345678', 'Groningen', 'https://source.example/noord', 'manual', 'community', 'motorcycle_club', 'Actieve club'],
        ]);

        $this->artisan('garagebook:growth-import-community2026', ['--file' => $path])->assertSuccessful();

        $this->assertDatabaseHas('growth_campaigns', ['slug' => 'community2026', 'name' => 'Community2026']);
        $this->assertDatabaseHas('growth_prospects', [
            'name' => 'Motorclub Noord',
            'normalized_domain' => 'motorclub-noord.example',
            'normalized_email' => 'info@motorclub-noord.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'prospect_type' => 'community',
            'prospect_subtype' => 'motorcycle_club',
        ]);
        $this->assertDatabaseHas('growth_outreach_events', ['event_type' => GrowthOutreachEvent::TYPE_IMPORTED]);
    }

    public function test_import_updates_existing_prospect_on_domain_or_email(): void
    {
        $existing = GrowthProspect::factory()->create([
            'name' => 'Oude naam',
            'website' => 'https://club.example',
            'normalized_domain' => 'club.example',
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
        ]);
        $path = $this->writeImportCsv('community-update.csv', [
            ['Nieuwe naam', 'https://club.example', 'info@club.example', '', 'Utrecht', 'https://source.example/club', 'manual', 'community', 'brand_club', 'Bijgewerkt'],
        ]);

        $this->artisan('garagebook:growth-import-community2026', ['--file' => $path])->assertSuccessful();

        $existing->refresh();
        $this->assertSame('Oude naam', $existing->name);
        $this->assertSame('info@club.example', $existing->email);
        $this->assertSame(GrowthProspect::EMAIL_STATUS_FOUND, $existing->email_status);
        $this->assertDatabaseCount('growth_prospects', 1);
        $this->assertDatabaseHas('growth_outreach_events', [
            'growth_prospect_id' => $existing->id,
            'event_type' => GrowthOutreachEvent::TYPE_ENRICHED,
        ]);
    }

    public function test_prospect_without_email_gets_missing_status_and_verification_required(): void
    {
        $path = $this->writeImportCsv('community-missing-email.csv', [
            ['Camperclub Zuid', 'https://camperclub-zuid.example', '', '', 'Breda', 'https://source.example/camper', 'manual', 'community', 'camper_club', 'Geen mail'],
        ]);

        app(Community2026ImportService::class)->importPath($path);

        $prospect = GrowthProspect::query()->where('name', 'Camperclub Zuid')->firstOrFail();
        $this->assertSame(GrowthProspect::EMAIL_STATUS_MISSING, $prospect->email_status);
        $this->assertTrue($prospect->verification_required);
    }

    public function test_duplicate_prospect_is_not_emailed(): void
    {
        Mail::fake();
        $campaign = $this->communityCampaign();
        $master = GrowthProspect::factory()->create();
        $duplicate = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
            'duplicate_of_id' => $master->id,
            'email' => 'dup@example.com',
            'normalized_email' => 'dup@example.com',
            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
        ]);

        $sent = app(GrowthProspectOutreachService::class)->sendCampaign($duplicate, 'community2026');

        $this->assertFalse($sent);
        Mail::assertNothingSent();
        $this->assertSame(GrowthCampaignEligibilityService::REASON_DUPLICATE, $duplicate->fresh()->skip_reason);
        $this->assertSkippedEvent($duplicate, GrowthCampaignEligibilityService::REASON_DUPLICATE);
    }

    public function test_recent_club2026_contact_skips_community2026_within_90_days(): void
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        try {
            $club = GrowthCampaign::factory()->create(['slug' => 'club2026']);
            $community = $this->communityCampaign();
            $previous = GrowthProspect::factory()->create([
                'campaign_id' => $club->id,
                'normalized_domain' => 'sameclub.example',
            ]);
            GrowthOutreachEvent::query()->create([
                'growth_prospect_id' => $previous->id,
                'campaign_id' => $club->id,
                'campaign_slug' => 'club2026',
                'event_type' => GrowthOutreachEvent::TYPE_SENT,
                'occurred_at' => now()->subDays(30),
            ]);
            $prospect = GrowthProspect::factory()->create([
                'campaign_id' => $community->id,
                'website' => 'https://sameclub.example',
                'normalized_domain' => 'sameclub.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            ]);

            $this->artisan('garagebook:queue-growth-campaign', ['campaign_slug' => 'community2026'])
                ->expectsOutput('skipped already contacted: 1')
                ->assertSuccessful();

            $this->assertSame(GrowthCampaignEligibilityService::REASON_ALREADY_CONTACTED_RECENTLY, $prospect->fresh()->skip_reason);
            $this->assertSkippedEvent($prospect, GrowthCampaignEligibilityService::REASON_ALREADY_CONTACTED_RECENTLY);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_same_campaign_sent_event_skips_community2026(): void
    {
        $campaign = $this->communityCampaign();
        $prospect = GrowthProspect::factory()->create(['campaign_id' => $campaign->id]);
        GrowthOutreachEvent::query()->create([
            'growth_prospect_id' => $prospect->id,
            'campaign_id' => $campaign->id,
            'campaign_slug' => 'community2026',
            'event_type' => GrowthOutreachEvent::TYPE_SENT,
            'occurred_at' => now()->subDays(120),
        ]);

        $this->artisan('garagebook:queue-growth-campaign', ['campaign_slug' => 'community2026'])->assertSuccessful();

        $this->assertSame(GrowthCampaignEligibilityService::REASON_ALREADY_RECEIVED_CAMPAIGN, $prospect->fresh()->skip_reason);
        $this->assertSkippedEvent($prospect, GrowthCampaignEligibilityService::REASON_ALREADY_RECEIVED_CAMPAIGN);
    }

    public function test_archived_and_missing_email_prospects_are_skipped(): void
    {
        $campaign = $this->communityCampaign();
        $archived = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => 'archived',
            'lifecycle_status' => GrowthProspect::LIFECYCLE_ARCHIVED,
        ]);
        $missing = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
        ]);

        app(GrowthProspectOutreachService::class)->sendCampaign($archived, 'community2026');
        app(GrowthProspectOutreachService::class)->sendCampaign($missing, 'community2026');

        $this->assertSame(GrowthCampaignEligibilityService::REASON_ARCHIVED, $archived->fresh()->skip_reason);
        $this->assertSame(GrowthCampaignEligibilityService::REASON_MISSING_EMAIL, $missing->fresh()->skip_reason);
        $this->assertSkippedEvent($archived, GrowthCampaignEligibilityService::REASON_ARCHIVED);
        $this->assertSkippedEvent($missing, GrowthCampaignEligibilityService::REASON_MISSING_EMAIL);
    }

    public function test_sent_event_is_recorded_after_successful_send(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-01 09:00:00');

        try {
            $campaign = $this->communityCampaign();
            $prospect = GrowthProspect::factory()->create([
                'campaign_id' => $campaign->id,
                'partner_slug' => 'community-club',
                'email' => 'community@example.com',
                'normalized_email' => 'community@example.com',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            ]);

            $this->assertTrue(app(GrowthProspectOutreachService::class)->sendCampaign($prospect, 'community2026'));

            Mail::assertSent(GrowthProspectOutreachMail::class);
            $prospect->refresh();
            $this->assertSame(GrowthProspect::LIFECYCLE_CONTACTED, $prospect->lifecycle_status);
            $this->assertSame('community2026', $prospect->last_campaign_slug);
            $this->assertDatabaseHas('growth_outreach_events', [
                'growth_prospect_id' => $prospect->id,
                'campaign_slug' => 'community2026',
                'event_type' => GrowthOutreachEvent::TYPE_SENT,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_queue_command_logs_queued_events_for_ready_prospects(): void
    {
        $campaign = $this->communityCampaign();
        $prospect = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            'verification_required' => false,
        ]);

        $this->artisan('garagebook:queue-growth-campaign', ['campaign_slug' => 'community2026'])
            ->expectsOutput('total considered: 1')
            ->expectsOutput('queued: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('growth_outreach_events', [
            'growth_prospect_id' => $prospect->id,
            'campaign_slug' => 'community2026',
            'event_type' => GrowthOutreachEvent::TYPE_QUEUED,
        ]);
    }

    private function communityCampaign(): GrowthCampaign
    {
        return GrowthCampaign::query()->updateOrCreate(
            ['slug' => 'community2026'],
            ['name' => 'Community2026', 'description' => 'Community test', 'status' => GrowthCampaign::STATUS_DRAFT],
        );
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeImportCsv(string $name, array $rows): string
    {
        $path = storage_path('framework/testing/'.$name);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, ['name', 'website', 'email', 'phone', 'city', 'source_url', 'source_type', 'prospect_type', 'prospect_subtype', 'notes']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function assertSkippedEvent(GrowthProspect $prospect, string $reason): void
    {
        $this->assertDatabaseHas('growth_outreach_events', [
            'growth_prospect_id' => $prospect->id,
            'event_type' => GrowthOutreachEvent::TYPE_SKIPPED,
            'reason' => $reason,
        ]);
    }
}
