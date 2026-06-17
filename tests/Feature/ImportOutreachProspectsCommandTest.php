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

    public function test_command_creates_new_prospect_and_generates_token_automatically(): void
    {
        $campaignSlug = 'motorgarages-2026-01';
        $path = $this->writeCsv('test-create.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Breda,Breda,motobreda.nl,info@motobreda.nl,Jan,Warme lead',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => $campaignSlug,
        ])->assertSuccessful();

        $campaign = OutreachCampaign::query()->where('slug', $campaignSlug)->firstOrFail();
        $prospect = OutreachProspect::query()->where('company_name', 'Moto Breda')->firstOrFail();

        $this->assertSame(1, $campaign->prospects()->count());
        $this->assertNotNull($prospect->token);
        $this->assertSame('https://app.garagebook.nl/demo/garage/' . $prospect->token, $prospect->demoUrl());
        $this->assertFalse($prospect->emailLogs()->where('status', 'sent')->exists());
    }

    public function test_duplicate_on_website_is_skipped_by_default(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $existing = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Breda',
            'website' => 'https://motobreda.nl',
            'email' => 'info@motobreda.nl',
            'city' => 'Breda',
        ]);

        $path = $this->writeCsv('test-duplicate-website.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Breda Nieuw,Breda,https://www.motobreda.nl,nieuw@motobreda.nl,Jan,Geupdate notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'motorgarages-2026-01',
        ])->assertSuccessful();

        $this->assertSame(1, OutreachProspect::query()->count());
        $this->assertSame($existing->id, OutreachProspect::query()->first()->id);
        $this->assertSame('Moto Breda', $existing->fresh()->company_name);
    }

    public function test_duplicate_on_company_name_is_skipped_by_default(): void
    {
        OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $existing = OutreachProspect::factory()->create([
            'company_name' => 'Moto Tilburg',
            'website' => 'mototilburg.nl',
            'email' => 'info@mototilburg.nl',
            'city' => 'Tilburg',
        ]);

        $path = $this->writeCsv('test-duplicate-company.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Tilburg,Tilburg,https://anderewebsite.nl,ander@mototilburg.nl,Piet,Andere notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'motorgarages-2026-01',
        ])->assertSuccessful();

        $this->assertSame(1, OutreachProspect::query()->count());
        $this->assertSame($existing->id, OutreachProspect::query()->first()->id);
        $this->assertSame('Tilburg', $existing->fresh()->city);
    }

    public function test_duplicate_on_email_is_skipped_by_default(): void
    {
        OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $existing = OutreachProspect::factory()->create([
            'company_name' => 'Moto Utrecht',
            'website' => 'motoutrecht.nl',
            'email' => 'info@motoutrecht.nl',
            'city' => 'Utrecht',
        ]);

        $path = $this->writeCsv('test-duplicate-email.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Utrecht Nieuw,Nieuwegein,nieuwbedrijf.nl,INFO@MOTOUTRECHT.NL,Piet,Andere notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'motorgarages-2026-01',
        ])->assertSuccessful();

        $this->assertSame(1, OutreachProspect::query()->count());
        $this->assertSame($existing->id, OutreachProspect::query()->first()->id);
        $this->assertSame('Utrecht', $existing->fresh()->city);
    }

    public function test_existing_prospect_is_not_moved_without_explicit_option(): void
    {
        $sourceCampaign = OutreachCampaign::factory()->create(['slug' => 'existing-campaign']);
        OutreachCampaign::factory()->create(['slug' => 'target-campaign']);
        $existing = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $sourceCampaign->id,
            'company_name' => 'Moto Haarlem',
            'website' => 'motohaarlem.nl',
            'email' => 'info@motohaarlem.nl',
        ]);

        $path = $this->writeCsv('test-no-move.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Haarlem,Haarlem,motohaarlem.nl,info@motohaarlem.nl,Jan,Notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'target-campaign',
        ])->assertSuccessful();

        $this->assertSame($sourceCampaign->id, $existing->fresh()->outreach_campaign_id);
        $this->assertSame(1, OutreachProspect::query()->count());
        $this->assertSame(0, OutreachCampaign::query()->where('slug', 'target-campaign')->first()->prospects()->count());
    }

    public function test_existing_prospect_is_only_moved_with_explicit_option(): void
    {
        $sourceCampaign = OutreachCampaign::factory()->create(['slug' => 'existing-campaign']);
        $targetCampaign = OutreachCampaign::factory()->create(['slug' => 'target-campaign']);
        $existing = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $sourceCampaign->id,
            'company_name' => 'Moto Den Bosch',
            'website' => 'motodbosch.nl',
            'email' => 'info@motodbosch.nl',
        ]);

        $path = $this->writeCsv('test-move.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Den Bosch,Den Bosch,motodbosch.nl,info@motodbosch.nl,Jan,Notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'target-campaign',
            '--move-existing-to-campaign' => true,
        ])->assertSuccessful();

        $this->assertSame($targetCampaign->id, $existing->fresh()->outreach_campaign_id);
        $this->assertSame(1, $targetCampaign->fresh()->prospects()->count());
        $this->assertSame(0, $sourceCampaign->fresh()->prospects()->count());
    }

    public function test_existing_fields_are_not_overwritten_without_update_existing(): void
    {
        OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $existing = OutreachProspect::factory()->create([
            'company_name' => 'Moto Leiden',
            'website' => 'motoleiden.nl',
            'email' => 'info@motoleiden.nl',
            'city' => 'Leiden',
            'contact_name' => 'Oud',
            'notes' => 'Bestaande notitie',
        ]);

        $path = $this->writeCsv('test-no-overwrite.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Leiden,Rotterdam,motoleiden.nl,nieuw@motoleiden.nl,Nieuw,Nieuwe notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'motorgarages-2026-01',
        ])->assertSuccessful();

        $fresh = $existing->fresh();
        $this->assertSame('Leiden', $fresh->city);
        $this->assertSame('Oud', $fresh->contact_name);
        $this->assertSame('Bestaande notitie', $fresh->notes);
        $this->assertSame('info@motoleiden.nl', $fresh->email);
    }

    public function test_empty_fields_are_only_filled_with_update_existing(): void
    {
        OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $existing = OutreachProspect::factory()->create([
            'company_name' => 'Moto Zwolle',
            'website' => 'motozwolle.nl',
            'email' => null,
            'city' => null,
            'contact_name' => null,
            'notes' => null,
        ]);

        $path = $this->writeCsv('test-update-existing.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Zwolle,Zwolle,motozwolle.nl,info@motozwolle.nl,Jan,Geupdate notitie',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => 'motorgarages-2026-01',
            '--update-existing' => true,
        ])->assertSuccessful();

        $fresh = $existing->fresh();
        $this->assertSame('Zwolle', $fresh->city);
        $this->assertSame('Jan', $fresh->contact_name);
        $this->assertSame('info@motozwolle.nl', $fresh->email);
        $this->assertSame('Geupdate notitie', $fresh->notes);
    }

    public function test_dry_run_does_not_change_database(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $existingCount = OutreachProspect::query()->count();
        $path = $this->writeCsv('test-dry-run.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Dry Run,Utrecht,motodryrun.nl,info@motodryrun.nl,Jan,Preview',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => $campaign->slug,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame($existingCount, OutreachProspect::query()->count());
        $this->assertSame(0, OutreachProspect::query()->where('company_name', 'Moto Dry Run')->count());
    }

    public function test_new_prospect_has_no_sent_mail_status_after_import(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'motorgarages-2026-01']);
        $path = $this->writeCsv('test-no-mail-status.csv', [
            'company_name,city,website,email,contact_name,notes',
            'Moto Nieuwegein,Nieuwegein,motonieuwegein.nl,info@motonieuwegein.nl,Jan,Note',
        ]);

        $this->artisan('garagebook:import-outreach-prospects', [
            'csv' => $path,
            '--campaign' => $campaign->slug,
        ])->assertSuccessful();

        $prospect = OutreachProspect::query()->where('company_name', 'Moto Nieuwegein')->firstOrFail();

        $this->assertFalse($prospect->emailLogs()->where('status', 'sent')->exists());
        $this->assertTrue($campaign->prospects()->whereDoesntHave('emailLogs', fn ($query) => $query->where('status', 'sent'))->whereKey($prospect->id)->exists());
    }

    private function writeCsv(string $filename, array $lines): string
    {
        $path = storage_path('app/outreach/' . $filename);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, $lines));

        return $path;
    }
}
