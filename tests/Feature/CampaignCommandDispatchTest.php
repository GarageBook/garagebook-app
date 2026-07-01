<?php

namespace Tests\Feature;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignCommandDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_discover_dispatches_for_partner_campaign(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $seedOutput = base_path('storage/app/imports/partner2026_seed_urls.txt');
        $output = base_path('storage/app/imports/partner2026_discovered.csv');
        File::delete($seedOutput);
        File::delete($output);

        $this->artisan('garagebook:campaign-discover', ['campaign' => 'partner2026'])
            ->assertSuccessful();

        $this->assertFileExists($seedOutput);
        $this->assertFileExists($output);
    }

    public function test_campaign_import_dispatches_for_partner_campaign(): void
    {
        $path = $this->writeTempFile('campaign-dispatch-partner.csv', implode(PHP_EOL, [
            'name,website,email,phone,city,source_url,source_type,prospect_type,prospect_subtype,notes,quality_score,quality_flags,quality_verdict,quality_reason',
            'Dispatch Test Shop,https://dispatch-test.example,info@dispatch-test.example,0612345678,Amsterdam,https://source.example/dispatch,csv,partner,tire_specialist,Dispatch,90,[],accepted,dispatch',
        ]));

        $this->artisan('garagebook:campaign-import', ['campaign' => 'partner2026', '--file' => $path])
            ->assertSuccessful();

        $this->assertDatabaseHas('growth_campaigns', [
            'slug' => 'partner2026',
        ]);
        $this->assertDatabaseHas('growth_prospects', [
            'name' => 'Dispatch Test Shop',
            'email' => 'info@dispatch-test.example',
        ]);
    }

    public function test_campaign_report_dispatches_ready_only_for_partner_campaign(): void
    {
        $seedPath = storage_path('app/imports/partner2026_seed_urls.txt');
        $discoveredPath = storage_path('app/imports/partner2026_discovered.csv');
        $reportPath = storage_path('app/imports/partner2026_ready_report.json');
        File::ensureDirectoryExists(dirname($seedPath));
        File::put($seedPath, 'https://dispatch-ready.example
');
        File::put($discoveredPath, 'name,website,email,phone,city,province,source_url,source_type,prospect_type,prospect_subtype,notes,quality_score,quality_flags,quality_verdict,quality_reason
Dispatch Ready,https://dispatch-ready.example,info@dispatch-ready.example,0612345678,Amsterdam,Noord-Holland,https://source.example/dispatch,website,partner,parts_webshop,Ready,91,[],accepted,dispatch
');

        $campaign = GrowthCampaign::query()->updateOrCreate(
            ['slug' => 'partner2026'],
            ['name' => 'Partner2026', 'description' => 'test', 'status' => GrowthCampaign::STATUS_DRAFT],
        );
        GrowthProspect::query()->create([
            'campaign_id' => $campaign->id,
            'name' => 'Dispatch Ready',
            'website' => 'https://dispatch-ready.example',
            'normalized_domain' => 'dispatch-ready.example',
            'email' => 'info@dispatch-ready.example',
            'normalized_email' => 'info@dispatch-ready.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_VERIFIED,
            'verification_required' => false,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_READY,
            'status' => GrowthProspect::LIFECYCLE_READY,
        ]);

        $this->artisan('garagebook:campaign-report', ['campaign' => 'partner2026', '--ready-only' => true])
            ->expectsOutput('Partner2026 readiness report.')
            ->expectsOutput('total ready for outreach: 1')
            ->expectsOutputToContain('Dispatch Ready')
            ->assertSuccessful();

        $this->assertFileExists($reportPath);
    }

    private function writeTempFile(string $name, string $contents): string
    {
        $path = storage_path('framework/testing/'.$name);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }
}
