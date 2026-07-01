<?php

namespace Tests\Feature;

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

    private function writeTempFile(string $name, string $contents): string
    {
        $path = storage_path('framework/testing/'.$name);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }
}
