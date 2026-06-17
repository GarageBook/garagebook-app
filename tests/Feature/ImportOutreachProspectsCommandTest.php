<?php

namespace Tests\Feature;

use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportOutreachProspectsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_campaign_and_imports_prospects(): void
    {
        $path = storage_path('app/outreach/test-motorgarages.csv');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, [
            'company_name,city,website,email,contact_name,notes',
            'Moto Breda,Breda,motobreda.nl,info@motobreda.nl,Jan,Warme lead',
            'Moto Tilburg,Tilburg,mototilburg.nl,info@mototilburg.nl,Piet,Follow-up',
        ]));

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => 'storage/app/outreach/test-motorgarages.csv',
            '--campaign' => 'motorgarages-2026-01',
        ])->assertSuccessful();

        $campaign = OutreachCampaign::query()->where('slug', 'motorgarages-2026-01')->first();

        $this->assertNotNull($campaign);
        $this->assertSame(2, OutreachProspect::query()->count());
        $this->assertTrue(OutreachProspect::query()->whereNotNull('token')->exists());
    }

    public function test_command_updates_existing_prospect_instead_of_creating_duplicate(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Breda',
            'website' => 'https://motobreda.nl',
            'city' => 'Oud',
        ]);

        $path = storage_path('app/outreach/test-motorgarages-duplicates.csv');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, [
            'company_name,city,website,email,contact_name,notes',
            'Moto Breda,Breda,motobreda.nl,nieuw@motobreda.nl,Jan,Geupdate notitie',
        ]));

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'motorgarages-2026-01',
        ])->assertSuccessful();

        $this->assertSame(1, OutreachProspect::query()->count());
        $this->assertSame($prospect->id, OutreachProspect::query()->first()->id);
        $this->assertSame('Breda', OutreachProspect::query()->first()->city);
        $this->assertSame('nieuw@motobreda.nl', OutreachProspect::query()->first()->email);
    }
}
