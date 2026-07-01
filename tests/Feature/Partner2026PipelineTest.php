<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Partner2026PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
    }

    public function test_default_seed_providers_write_at_least_250_seed_urls(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $seedOutput = base_path('storage/app/imports/partner2026_seed_urls.txt');
        $output = base_path('storage/app/imports/partner2026_discovered.csv');
        $rejected = base_path('storage/app/imports/partner2026_rejected.csv');
        File::delete($seedOutput);
        File::delete($output);
        File::delete($rejected);

        $this->artisan('garagebook:discover-partner2026')
            ->expectsOutput('Partner2026 discovery voltooid.')
            ->assertSuccessful();

        $this->assertFileExists($seedOutput);
        $this->assertFileExists($output);
        $this->assertFileExists($rejected);
        $this->assertGreaterThanOrEqual(250, count(file($seedOutput, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []));
    }

    public function test_import_cleanup_and_quality_filter_work_for_partner_campaign(): void
    {
        $campaign = $this->partnerCampaign();
        $path = $this->writeImportCsv('partner-import.csv', implode(PHP_EOL, [
            'name,website,email,phone,city,source_url,source_type,prospect_type,prospect_subtype,notes,quality_score,quality_flags,quality_verdict,quality_reason',
            'Banden Centrum Noord,https://bandencentrum-noord.example,info@bandencentrum-noord.example,0612345678,Groningen,https://source.example/banden,manual,partner,tire_specialist,Snel contact,90,[],accepted,partner prospect',
            'Tuning House Zuid,https://tuninghouse-zuid.example,,,,https://source.example/tuning,manual,partner,tuning,Geen mail,68,["no_email"],manual_review,missing email',
        ]));

        $this->artisan('garagebook:growth-import-partner2026', ['--file' => $path])->assertSuccessful();
        $this->artisan('garagebook:partner2026-cleanup')->assertSuccessful();
        $this->artisan('garagebook:partner2026-quality-filter')->assertSuccessful();

        $this->assertDatabaseHas('growth_campaigns', [
            'slug' => 'partner2026',
            'name' => 'Partner2026',
        ]);
        $this->assertDatabaseHas('growth_prospects', [
            'campaign_id' => $campaign->id,
            'name' => 'Banden Centrum Noord',
            'prospect_type' => 'partner',
            'prospect_subtype' => 'tire_specialist',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
        ]);
        $this->assertDatabaseHas('growth_prospects', [
            'campaign_id' => $campaign->id,
            'name' => 'Tuning House Zuid',
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_MANUAL_REVIEW,
        ]);
    }

    public function test_enrichment_promotes_high_confidence_mailto_to_found_and_ready(): void
    {
        Mail::fake();
        Queue::fake();
        $campaign = $this->partnerCampaign();
        $prospect = $this->missingProspect($campaign, 'https://bandenvoorbeeld.example');

        Http::fake([
            'https://bandenvoorbeeld.example/contact' => Http::response('<html><body><a href="mailto:info@bandenvoorbeeld.example">Mail ons</a></body></html>'),
            'https://bandenvoorbeeld.example/*' => Http::response('', 404),
            'https://bandenvoorbeeld.example' => Http::response('<html><body><a href="/contact">Contact</a></body></html>'),
        ]);

        $this->artisan('garagebook:partner2026-enrich', ['--limit' => 1])
            ->expectsOutput('e-mails automatisch gevonden: 1')
            ->assertSuccessful();

        $prospect->refresh();
        $this->assertSame('info@bandenvoorbeeld.example', $prospect->email);
        $this->assertSame(GrowthProspect::EMAIL_STATUS_FOUND, $prospect->email_status);
        $this->assertFalse($prospect->verification_required);
        $this->assertSame(GrowthProspect::LIFECYCLE_READY, $prospect->lifecycle_status);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_report_summarizes_partner_pipeline_and_lists_top_rows(): void
    {
        $campaign = $this->partnerCampaign();
        $seedPath = storage_path('app/imports/partner2026_seed_urls.txt');
        $discoveredPath = storage_path('app/imports/partner2026_discovered.csv');
        $reportPath = storage_path('app/imports/partner2026_report.json');
        File::ensureDirectoryExists(dirname($seedPath));
        File::put($seedPath, implode(PHP_EOL, [
            'https://ready.example',
            'https://manual.example',
        ]).PHP_EOL);
        File::put($discoveredPath, implode(PHP_EOL, [
            'name,website,email,phone,city,province,source_url,source_type,prospect_type,prospect_subtype,notes,quality_score,quality_flags,quality_verdict,quality_reason',
            'Ready Shop,https://ready.example,info@ready.example,0612345678,Rotterdam,Zuid-Holland,https://source.example/ready,website,partner,parts_webshop,Ready,92,[],accepted,ready',
        ]).PHP_EOL);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Ready Shop',
            'website' => 'https://ready.example',
            'normalized_domain' => 'ready.example',
            'email' => 'info@ready.example',
            'normalized_email' => 'info@ready.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Manual Review Shop',
            'website' => 'https://manual.example',
            'normalized_domain' => 'manual.example',
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
            'verification_required' => true,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_MANUAL_REVIEW,
            'status' => GrowthProspect::LIFECYCLE_MANUAL_REVIEW,
            'skip_reason' => 'manual_review_required',
        ]);

        $this->artisan('garagebook:partner2026-report')
            ->expectsOutput('seed urls: 2')
            ->expectsOutput('ready for outreach: 1')
            ->expectsOutput('manual review: 1')
            ->expectsOutputToContain('Ready Shop')
            ->expectsOutputToContain('Manual Review Shop')
            ->assertSuccessful();

        $this->assertFileExists($reportPath);
        $report = json_decode((string) file_get_contents($reportPath), true);
        $this->assertSame(2, $report['seed urls']);
        $this->assertSame(1, $report['ready for outreach']);
        $this->assertSame(1, $report['manual review']);
        $this->assertSame(1, $report['duplicates/skipped']);
    }

    public function test_ready_only_report_shows_safe_ready_count_and_exclusions(): void
    {
        Carbon::setTestNow('2026-07-02 09:00:00');

        try {
            $campaign = $this->partnerCampaign();
            $seedPath = storage_path('app/imports/partner2026_seed_urls.txt');
            $discoveredPath = storage_path('app/imports/partner2026_discovered.csv');
            $reportPath = storage_path('app/imports/partner2026_ready_report.json');
            File::ensureDirectoryExists(dirname($seedPath));
            File::put($seedPath, 'https://ready.example
https://recent.example
https://duplicate.example
https://missing.example
');
            File::put($discoveredPath, 'name,website,email,phone,city,province,source_url,source_type,prospect_type,prospect_subtype,notes,quality_score,quality_flags,quality_verdict,quality_reason
Ready Shop,https://ready.example,info@ready.example,0612345678,Rotterdam,Zuid-Holland,https://source.example/ready,website,partner,parts_webshop,Ready,95,[],accepted,ready
');

            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Ready Shop',
                'website' => 'https://ready.example',
                'normalized_domain' => 'ready.example',
                'email' => 'info@ready.example',
                'normalized_email' => 'info@ready.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 95,
                'last_contacted_at' => now()->subDays(120),
            ]);

            $recent = GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Recent Contact Shop',
                'website' => 'https://recent.example',
                'normalized_domain' => 'recent.example',
                'email' => 'info@recent.example',
                'normalized_email' => 'info@recent.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 88,
            ]);
            GrowthOutreachEvent::query()->create([
                'growth_prospect_id' => $recent->id,
                'campaign_id' => $campaign->id,
                'campaign_slug' => 'club2026',
                'event_type' => GrowthOutreachEvent::TYPE_SENT,
                'occurred_at' => now()->subDays(10),
            ]);

            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Already Received Shop',
                'website' => 'https://received.example',
                'normalized_domain' => 'received.example',
                'email' => 'info@received.example',
                'normalized_email' => 'info@received.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 79,
            ]);
            GrowthOutreachEvent::query()->create([
                'growth_prospect_id' => GrowthProspect::query()->where('normalized_domain', 'received.example')->firstOrFail()->id,
                'campaign_id' => $campaign->id,
                'campaign_slug' => 'partner2026',
                'event_type' => GrowthOutreachEvent::TYPE_SENT,
                'occurred_at' => now()->subDays(10),
            ]);

            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Duplicate Shop',
                'website' => 'https://duplicate.example',
                'normalized_domain' => 'duplicate.example',
                'email' => 'info@duplicate.example',
                'normalized_email' => 'info@duplicate.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'duplicate_of_id' => $recent->id,
                'quality_score' => 76,
            ]);

            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Missing Email Shop',
                'website' => 'https://missing.example',
                'normalized_domain' => 'missing.example',
                'email' => null,
                'normalized_email' => null,
                'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
                'verification_required' => true,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_ENRICHED,
                'status' => GrowthProspect::LIFECYCLE_ENRICHED,
                'quality_score' => 61,
            ]);

            $this->artisan('garagebook:campaign-report', ['campaign' => 'partner2026', '--ready-only' => true])
                ->expectsOutput('Partner2026 readiness report.')
                ->expectsOutput('total ready for outreach: 1')
                ->expectsOutput('excluded recent contacted: 1')
                ->expectsOutput('excluded already received campaign: 1')
                ->expectsOutput('excluded duplicate: 1')
                ->expectsOutput('excluded invalid/missing email: 1')
                ->expectsOutputToContain('Ready Shop')
                ->assertSuccessful();

            $this->assertFileExists($reportPath);
            $report = json_decode((string) file_get_contents($reportPath), true);
            $this->assertSame(1, $report['total ready for outreach']);
            $this->assertSame(1, $report['excluded recent contacted']);
            $this->assertSame(1, $report['excluded already received campaign']);
            $this->assertSame(1, $report['excluded duplicate']);
            $this->assertSame(1, $report['excluded invalid/missing email']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_queue_dry_run_lists_safe_pilot_batch_without_writing(): void
    {
        Carbon::setTestNow('2026-07-02 09:00:00');

        try {
            $campaign = $this->partnerCampaign();
            $safeOne = GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Safe Shop 1',
                'website' => 'https://safe1.example',
                'normalized_domain' => 'safe1.example',
                'email' => 'info@safe1.example',
                'normalized_email' => 'info@safe1.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 94,
            ]);
            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Suggested Shop',
                'website' => 'https://suggested.example',
                'normalized_domain' => 'suggested.example',
                'suggested_email' => 'contact@suggested.example',
                'suggested_email_confidence' => 80,
                'email' => 'contact@suggested.example',
                'normalized_email' => 'contact@suggested.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 70,
            ]);
            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Personal Mail Shop',
                'website' => 'https://personal.example',
                'normalized_domain' => 'personal.example',
                'email' => 'info@gmail.com',
                'normalized_email' => 'info@gmail.com',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 82,
            ]);
            GrowthProspect::query()->create([
                'campaign_id' => $campaign->id,
                'name' => 'Recent Shop',
                'website' => 'https://recentpilot.example',
                'normalized_domain' => 'recentpilot.example',
                'email' => 'info@recentpilot.example',
                'normalized_email' => 'info@recentpilot.example',
                'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
                'verification_required' => false,
                'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
                'status' => GrowthProspect::LIFECYCLE_READY,
                'quality_score' => 79,
            ]);

            $this->artisan('garagebook:queue-growth-campaign', ['campaign_slug' => 'partner2026', '--limit' => 50, '--dry-run' => true])
                ->expectsOutputToContain('Dry-run pilot queue voor partner2026.')
                ->expectsOutputToContain('skipped personal email: 1')
                ->expectsOutputToContain('skipped suggested email: 1')
                ->expectsOutputToContain('Safe Shop 1')
                ->assertSuccessful();

            $this->assertDatabaseCount('growth_outreach_events', 0);
            $safeOne->refresh();
            $this->assertNull($safeOne->skip_reason);
            $this->assertNull($safeOne->last_campaign_slug);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_queue_limit_25_queues_only_safe_prospects(): void
    {
        $campaign = $this->partnerCampaign();

        $safeOne = GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Pilot Safe 1',
            'website' => 'https://pilot1.example',
            'normalized_domain' => 'pilot1.example',
            'email' => 'info@pilot1.example',
            'normalized_email' => 'info@pilot1.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);
        $safeTwo = GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Pilot Safe 2',
            'website' => 'https://pilot2.example',
            'normalized_domain' => 'pilot2.example',
            'email' => 'info@pilot2.example',
            'normalized_email' => 'info@pilot2.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);
        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Pilot Suggested',
            'website' => 'https://pilotsuggested.example',
            'normalized_domain' => 'pilotsuggested.example',
            'suggested_email' => 'info@pilotsuggested.example',
            'suggested_email_confidence' => 80,
            'email' => 'info@pilotsuggested.example',
            'normalized_email' => 'info@pilotsuggested.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        $this->artisan('garagebook:queue-growth-campaign', ['campaign_slug' => 'partner2026', '--limit' => 25])
            ->expectsOutput('Pilot queue klaar voor partner2026.')
            ->expectsOutput('queued: 2')
            ->expectsOutput('skipped suggested email: 1')
            ->assertSuccessful();

        $safeOne->refresh();
        $safeTwo->refresh();
        $this->assertSame($campaign->id, $safeOne->campaign_id);
        $this->assertSame('partner2026', $safeOne->last_campaign_slug);
        $this->assertSame($campaign->id, $safeTwo->campaign_id);
        $this->assertSame('partner2026', $safeTwo->last_campaign_slug);
        $this->assertDatabaseHas('growth_outreach_events', [
            'growth_prospect_id' => $safeOne->id,
            'campaign_slug' => 'partner2026',
            'event_type' => GrowthOutreachEvent::TYPE_QUEUED,
        ]);
        $this->assertDatabaseHas('growth_outreach_events', [
            'growth_prospect_id' => $safeTwo->id,
            'campaign_slug' => 'partner2026',
            'event_type' => GrowthOutreachEvent::TYPE_QUEUED,
        ]);
    }

    private function partnerCampaign(): GrowthCampaign
    {
        return GrowthCampaign::query()->updateOrCreate(
            ['slug' => 'partner2026'],
            [
                'name' => 'Partner2026',
                'description' => 'Gespecialiseerde bedrijven rondom onderhoud, onderdelen, banden, detailing, tuning, vering, remmen, oldtimers, campers, 4x4 en motoraccessoires.',
                'status' => GrowthCampaign::STATUS_DRAFT,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function missingProspect(GrowthCampaign $campaign, string $website, array $overrides = []): GrowthProspect
    {
        return GrowthProspect::factory()->create(array_merge([
            'campaign_id' => $campaign->id,
            'website' => $website,
            'normalized_domain' => parse_url($website, PHP_URL_HOST),
            'email' => null,
            'normalized_email' => null,
            'email_status' => GrowthProspect::EMAIL_STATUS_MISSING,
            'verification_required' => true,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_ENRICHED,
            'status' => GrowthProspect::LIFECYCLE_ENRICHED,
            'skip_reason' => 'missing_email',
        ], $overrides));
    }

    private function writeImportCsv(string $name, string $contents): string
    {
        $path = storage_path('framework/testing/'.$name);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }
}
