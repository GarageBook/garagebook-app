<?php

namespace Tests\Feature;

use App\Filament\Resources\GrowthProspects\Pages\CreateGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\EditGrowthProspect;
use App\Filament\Resources\GrowthProspects\Pages\ImportGrowthProspects;
use App\Filament\Resources\GrowthProspects\Pages\ListGrowthProspects;
use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use App\Models\User;
use App\Services\Growth\GrowthProspectCsvImportService;
use App\Services\Growth\GrowthProspectTrackingUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthProspectResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_growth_prospect_index(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Club2026',
        ]);

        GrowthProspect::factory()->create([
            'name' => 'Motorclub Noord',
            'category' => 'club',
            'campaign_id' => $campaign->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/growth-prospects')
            ->assertOk()
            ->assertSeeText('Motorclub Noord');
    }

    public function test_admin_can_create_growth_prospect(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Event2026',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateGrowthProspect::class)
            ->fillForm([
                'name' => 'Circuit Partner',
                'website' => 'circuit.example',
                'category' => 'event',
                'subcategory' => 'trackday',
                'region' => 'Noord-Holland',
                'estimated_reach' => '1.000+',
                'newsletter_status' => 'available',
                'primary_contact_channel' => 'email',
                'contact_name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'priority' => 'high',
                'warmth' => 'warm',
                'score' => 82,
                'status' => 'new',
                'campaign_id' => $campaign->id,
                'partner_slug' => 'circuit-partner',
                'notes' => 'Heeft een actieve community.',
                'why_interesting' => 'Bereikt sportieve motorrijders.',
                'approach_strategy' => 'Benaderen met event-specifieke onderhoudsboek propositie.',
                'last_contacted_at' => null,
                'next_follow_up_at' => '2026-07-10 09:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('growth_prospects', [
            'name' => 'Circuit Partner',
            'category' => 'event',
            'campaign_id' => $campaign->id,
            'partner_slug' => 'circuit-partner',
            'priority' => 'high',
            'warmth' => 'warm',
            'score' => 82,
            'status' => 'new',
        ]);
    }

    public function test_admin_can_update_growth_prospect(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Workshop2026',
        ]);
        $prospect = GrowthProspect::factory()->create([
            'name' => 'Oude werkplaats',
            'campaign_id' => null,
            'partner_slug' => 'oude-werkplaats',
            'status' => 'new',
        ]);

        Livewire::actingAs($admin)
            ->test(EditGrowthProspect::class, ['record' => $prospect->getRouteKey()])
            ->fillForm([
                'name' => 'Bijgewerkte werkplaats',
                'website' => 'werkplaats.example',
                'category' => 'workshop',
                'subcategory' => 'maintenance',
                'region' => 'Utrecht',
                'estimated_reach' => '500-1.000',
                'newsletter_status' => 'unknown',
                'primary_contact_channel' => 'contact_form',
                'contact_name' => 'John Doe',
                'email' => 'john@example.com',
                'priority' => 'medium',
                'warmth' => 'hot',
                'score' => 91,
                'status' => 'contacted',
                'campaign_id' => $campaign->id,
                'partner_slug' => 'bijgewerkte-werkplaats',
                'notes' => 'Nieuwe notitie.',
                'why_interesting' => 'Veel klanten met onderhoudsvragen.',
                'approach_strategy' => 'Persoonlijke demo aanbieden.',
                'last_contacted_at' => '2026-07-01 10:30:00',
                'next_follow_up_at' => '2026-07-08 10:30:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $prospect->refresh();

        $this->assertSame('Bijgewerkte werkplaats', $prospect->name);
        $this->assertSame('contacted', $prospect->status);
        $this->assertSame('hot', $prospect->warmth);
        $this->assertSame(91, $prospect->score);
        $this->assertTrue($prospect->campaign->is($campaign));
    }

    public function test_growth_prospect_belongs_to_growth_campaign(): void
    {
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Media2026',
        ]);
        $prospect = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $this->assertTrue($prospect->campaign->is($campaign));
    }

    public function test_growth_prospect_tracking_url_uses_partner_and_campaign_slugs(): void
    {
        $campaign = GrowthCampaign::factory()->create([
            'slug' => 'club2026',
        ]);
        $prospect = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
            'partner_slug' => 'motorclub-noord',
        ]);

        $this->assertSame(
            url('/start?utm_source=motorclub-noord&utm_medium=partner&utm_campaign=club2026&partner_slug=motorclub-noord&campaign_slug=club2026'),
            app(GrowthProspectTrackingUrlGenerator::class)->generate($prospect),
        );
    }

    public function test_growth_prospect_tracking_url_is_null_without_partner_slug(): void
    {
        $campaign = GrowthCampaign::factory()->create([
            'slug' => 'club2026',
        ]);
        $prospect = GrowthProspect::factory()->create([
            'campaign_id' => $campaign->id,
            'partner_slug' => null,
        ]);

        $this->assertNull(app(GrowthProspectTrackingUrlGenerator::class)->generate($prospect));
    }

    public function test_growth_prospect_tracking_url_is_null_without_campaign(): void
    {
        $prospect = GrowthProspect::factory()->create([
            'campaign_id' => null,
            'partner_slug' => 'motorclub-noord',
        ]);

        $this->assertNull(app(GrowthProspectTrackingUrlGenerator::class)->generate($prospect));
    }

    public function test_growth_prospect_partner_slug_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();

        GrowthProspect::factory()->create([
            'partner_slug' => 'unique-partner',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateGrowthProspect::class)
            ->fillForm([
                'name' => 'Duplicaat partner',
                'partner_slug' => 'unique-partner',
            ])
            ->call('create')
            ->assertHasFormErrors(['partner_slug' => 'unique']);
    }

    public function test_growth_prospect_list_supports_relevant_query_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $campaign = GrowthCampaign::factory()->create([
            'name' => 'Training2026',
        ]);

        GrowthProspect::factory()->create([
            'name' => 'Training Partner',
            'campaign_id' => $campaign->id,
            'category' => 'training',
            'priority' => 'high',
            'warmth' => 'warm',
            'status' => 'researching',
        ]);
        GrowthProspect::factory()->create([
            'name' => 'Andere Partner',
            'category' => 'media',
            'priority' => 'low',
            'warmth' => 'cold',
            'status' => 'paused',
        ]);

        Livewire::actingAs($admin)
            ->test(ListGrowthProspects::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(GrowthProspect::query()
                ->where('campaign_id', $campaign->id)
                ->where('category', 'training')
                ->where('priority', 'high')
                ->where('warmth', 'warm')
                ->where('status', 'researching')
                ->get());
    }

    public function test_admin_can_open_growth_prospect_import_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/growth-prospects/import')
            ->assertOk()
            ->assertSeeText('Import prospects')
            ->assertSeeText('CSV upload');
    }

    public function test_admin_can_preview_growth_prospect_csv_upload(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $csv = UploadedFile::fake()->createWithContent('prospects.csv', implode("\n", [
            'name,website,category,email,contact_name,region,priority,warmth,score,status,notes,partner_slug',
            'Motorclub Noord,https://noord.example,club,info@noord.example,Jan Noord,Groningen,high,warm,80,new,Eerste notitie,motorclub-noord',
        ]));

        Livewire::actingAs($admin)
            ->test(ImportGrowthProspects::class)
            ->set('csvFile', $csv)
            ->call('uploadCsv')
            ->assertSet('summary.new', 1)
            ->assertSet('summary.update', 0)
            ->assertSet('summary.skipped', 0)
            ->assertSee('Motorclub Noord');
    }

    public function test_growth_prospect_csv_preview_is_limited_to_first_twenty_rows(): void
    {
        $importer = app(GrowthProspectCsvImportService::class);
        $path = tempnam(sys_get_temp_dir(), 'growth-prospects-');
        $lines = ['name,email'];

        for ($i = 1; $i <= 25; $i++) {
            $lines[] = 'Prospect '.$i.',prospect'.$i.'@example.com';
        }

        file_put_contents($path, implode("\n", $lines));

        try {
            $parsed = $importer->parsePath($path);
            $analysis = $importer->analyze($parsed, $importer->defaultMapping($parsed['headers']));

            $this->assertCount(20, $analysis['preview']);
            $this->assertSame(25, $analysis['summary']['new']);
        } finally {
            @unlink($path);
        }
    }

    public function test_growth_prospect_csv_detects_existing_duplicates_as_updates(): void
    {
        GrowthProspect::factory()->create([
            'name' => 'Bestaande prospect',
            'website' => 'https://duplicate.example',
            'email' => 'old@example.com',
            'partner_slug' => 'old-partner',
        ]);

        $analysis = $this->analyzeGrowthProspectCsv(implode("\n", [
            'name,website,email,partner_slug',
            'Nieuwe naam,https://duplicate.example,new@example.com,new-partner',
        ]));

        $this->assertSame(0, $analysis['summary']['new']);
        $this->assertSame(1, $analysis['summary']['update']);
        $this->assertSame(0, $analysis['summary']['skipped']);
        $this->assertSame('update', $analysis['items'][0]['action']);
    }

    public function test_growth_prospect_csv_import_creates_new_prospects(): void
    {
        $result = $this->importGrowthProspectCsv(implode("\n", [
            'name,website,category,email,contact_name,region,priority,warmth,score,status,notes,partner_slug',
            'Nieuwe Partner,https://new.example,club,new@example.com,Nieuw Contact,Utrecht,high,warm,77,new,Import notitie,new-partner',
        ]));

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('growth_prospects', [
            'name' => 'Nieuwe Partner',
            'website' => 'https://new.example',
            'email' => 'new@example.com',
            'partner_slug' => 'new-partner',
            'score' => 77,
        ]);
    }

    public function test_growth_prospect_csv_import_updates_existing_prospect(): void
    {
        $existing = GrowthProspect::factory()->create([
            'name' => 'Oude naam',
            'website' => 'https://update.example',
            'email' => 'old@example.com',
            'partner_slug' => 'update-partner',
            'priority' => 'low',
        ]);

        $result = $this->importGrowthProspectCsv(implode("\n", [
            'name,website,email,priority,warmth,partner_slug',
            'Nieuwe naam,https://update.example,new@example.com,high,hot,update-partner',
        ]));

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        $existing->refresh();
        $this->assertSame('Nieuwe naam', $existing->name);
        $this->assertSame('new@example.com', $existing->email);
        $this->assertSame('high', $existing->priority);
        $this->assertSame('hot', $existing->warmth);
    }

    public function test_growth_prospect_csv_import_skips_duplicate_rows_in_same_file(): void
    {
        $result = $this->importGrowthProspectCsv(implode("\n", [
            'name,email',
            'Eerste,email@example.com',
            'Tweede,email@example.com',
        ]));

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('growth_prospects', ['name' => 'Eerste']);
        $this->assertDatabaseMissing('growth_prospects', ['name' => 'Tweede']);
    }

    public function test_growth_prospect_csv_import_skips_rows_without_name(): void
    {
        $result = $this->importGrowthProspectCsv(implode("\n", [
            'name,email',
            ',noname@example.com',
        ]));

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseMissing('growth_prospects', ['email' => 'noname@example.com']);
    }

    public function test_growth_prospect_csv_import_skips_rows_matching_multiple_existing_prospects(): void
    {
        GrowthProspect::factory()->create([
            'name' => 'Website match',
            'website' => 'https://multi.example',
            'email' => 'website@example.com',
            'partner_slug' => 'website-match',
        ]);
        GrowthProspect::factory()->create([
            'name' => 'Email match',
            'website' => 'https://other.example',
            'email' => 'multi@example.com',
            'partner_slug' => 'email-match',
        ]);

        $result = $this->importGrowthProspectCsv(implode("\n", [
            'name,website,email',
            'Ambiguous,https://multi.example,multi@example.com',
        ]));

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseMissing('growth_prospects', ['name' => 'Ambiguous']);
    }

    private function analyzeGrowthProspectCsv(string $csv): array
    {
        $importer = app(GrowthProspectCsvImportService::class);
        $path = tempnam(sys_get_temp_dir(), 'growth-prospects-');
        file_put_contents($path, $csv);

        try {
            $parsed = $importer->parsePath($path);

            return $importer->analyze($parsed, $importer->defaultMapping($parsed['headers']));
        } finally {
            @unlink($path);
        }
    }

    private function importGrowthProspectCsv(string $csv): array
    {
        $importer = app(GrowthProspectCsvImportService::class);
        $path = tempnam(sys_get_temp_dir(), 'growth-prospects-');
        file_put_contents($path, $csv);

        try {
            $parsed = $importer->parsePath($path);

            return $importer->import($parsed, $importer->defaultMapping($parsed['headers']));
        } finally {
            @unlink($path);
        }
    }
}
