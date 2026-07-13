<?php

namespace Tests\Feature;

use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use App\Mail\GrowthProspectOutreachMail;
use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use App\Models\OutreachCampaign;
use App\Models\OutreachProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MotorclubGrowthImportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
    }

    public function test_dry_run_writes_nothing_to_database(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://www.motorclub-noord.example', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--dry-run' => true,
        ])
            ->expectsOutput('read: 1')
            ->expectsOutput('create: 1')
            ->expectsOutput('0 outreachberichten gequeued')
            ->expectsOutput('0 outreachberichten verzonden')
            ->assertSuccessful();

        $this->assertDatabaseCount('growth_prospects', 0);
        $this->assertDatabaseCount('growth_outreach_events', 0);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_real_import_creates_valid_motorclub_records_without_mail(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://www.motorclub-noord.example', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--force' => true,
        ])
            ->expectsConfirmation('Dit schrijft motorclubprospects naar de lokale database. Er wordt niets gemaild. Doorgaan?', 'yes')
            ->assertSuccessful();

        $campaign = GrowthCampaign::query()->where('slug', 'club2026')->firstOrFail();
        $this->assertDatabaseHas('growth_prospects', [
            'name' => 'Motorclub Noord',
            'campaign_id' => $campaign->id,
            'prospect_type' => 'community',
            'prospect_subtype' => 'motorcycle_club',
            'normalized_domain' => 'motorclub-noord.example',
            'normalized_email' => 'info@motorclub-noord.example',
            'email_status' => GrowthProspect::EMAIL_STATUS_FOUND,
            'lifecycle_status' => GrowthProspect::LIFECYCLE_ENRICHED,
        ]);
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_reimport_does_not_create_duplicates(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://www.motorclub-noord.example', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->runWriteImport($csv, $markdown);
        $this->runWriteImport($csv, $markdown);

        $this->assertDatabaseCount('growth_prospects', 1);
    }

    public function test_domain_normalization_matches_www_and_protocol_variants(): void
    {
        $this->seedCampaigns();
        GrowthProspect::factory()->create([
            'website' => 'https://motorclub-noord.example',
            'normalized_domain' => 'motorclub-noord.example',
            'organization_key' => 'motorclub-noord.example',
            'email' => null,
            'normalized_email' => null,
        ]);
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'http://www.motorclub-noord.example/', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--dry-run' => true,
        ])
            ->expectsOutput('existing: 1')
            ->assertSuccessful();
    }

    public function test_email_normalization_prevents_duplicates(): void
    {
        $this->seedCampaigns();
        GrowthProspect::factory()->create([
            'email' => 'info@motorclub-noord.example',
            'normalized_email' => 'info@motorclub-noord.example',
        ]);
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://different.example', 'INFO@MOTORCLUB-NOORD.EXAMPLE', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--dry-run' => true,
        ])
            ->expectsOutput('existing: 1')
            ->assertSuccessful();
    }

    public function test_records_without_public_email_go_to_manual_review(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Club Zonder Mail', 'https://club-zonder-mail.example', '', 'Merkclub', 'BMW', 'Club2026'),
        ]);

        $this->runWriteImport($csv, $markdown);

        $prospect = GrowthProspect::query()->where('name', 'Club Zonder Mail')->firstOrFail();
        $this->assertSame(GrowthProspect::EMAIL_STATUS_MISSING, $prospect->email_status);
        $this->assertSame(GrowthProspect::LIFECYCLE_MANUAL_REVIEW, $prospect->lifecycle_status);
        $this->assertSame('missing_email', $prospect->skip_reason);
    }

    public function test_personal_email_addresses_are_not_auto_ready(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Persoonlijke Club', 'https://persoonlijke-club.example', 'clubbeheer@gmail.com', 'Merkclub', 'Yamaha', 'Club2026'),
        ]);

        $this->runWriteImport($csv, $markdown);

        $prospect = GrowthProspect::query()->where('name', 'Persoonlijke Club')->firstOrFail();
        $this->assertSame(GrowthProspect::LIFECYCLE_MANUAL_REVIEW, $prospect->lifecycle_status);
        $this->assertSame('personal_email', $prospect->skip_reason);
    }

    public function test_club2026_and_classic2026_mapping_is_correct(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Toerclub Noord', 'https://toerclub-noord.example', 'info@toerclub-noord.example', 'Toerclub', 'Motor-toerclub', 'Club2026'),
            $this->row('Veteraan Motorclub', 'https://veteraan-motorclub.example', 'info@veteraan-motorclub.example', 'Klassieke motorclub', 'Veteraan motorfietsen', 'Classic2026'),
        ]);

        $this->runWriteImport($csv, $markdown);

        $club = GrowthProspect::query()->where('name', 'Toerclub Noord')->firstOrFail();
        $classic = GrowthProspect::query()->where('name', 'Veteraan Motorclub')->firstOrFail();

        $this->assertSame('club2026', $club->campaign->slug);
        $this->assertSame('motorcycle_club', $club->prospect_subtype);
        $this->assertSame('classic2026', $classic->campaign->slug);
        $this->assertSame('oldtimer_club', $classic->prospect_subtype);
    }

    public function test_missing_campaign_fails_safely(): void
    {
        GrowthCampaign::factory()->create(['slug' => 'club2026', 'name' => 'Club2026']);
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Veteraan Motorclub', 'https://veteraan-motorclub.example', 'info@veteraan-motorclub.example', 'Klassieke motorclub', 'Veteraan motorfietsen', 'Classic2026'),
        ]);

        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--dry-run' => true,
        ])
            ->expectsOutput('Vereiste growth campagne ontbreekt: classic2026')
            ->expectsOutput('0 outreachberichten gequeued')
            ->expectsOutput('0 outreachberichten verzonden')
            ->assertFailed();

        $this->assertDatabaseCount('growth_prospects', 0);
    }

    public function test_import_never_queues_or_sends_mail(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://www.motorclub-noord.example', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->runWriteImport($csv, $markdown);

        Mail::assertNotSent(GrowthProspectOutreachMail::class);
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('growth_outreach_events', ['event_type' => GrowthOutreachEvent::TYPE_QUEUED]);
        $this->assertDatabaseMissing('growth_outreach_events', ['event_type' => GrowthOutreachEvent::TYPE_SENT]);
    }

    public function test_existing_outreach_history_is_not_overwritten(): void
    {
        $this->seedCampaigns();
        $legacyCampaign = OutreachCampaign::query()->create([
            'name' => 'Legacy',
            'slug' => 'legacy',
        ]);
        $legacy = OutreachProspect::query()->create([
            'outreach_campaign_id' => $legacyCampaign->id,
            'company_name' => 'Motorclub Noord',
            'email' => 'info@motorclub-noord.example',
            'website' => 'https://motorclub-noord.example',
            'token' => OutreachProspect::generateUniqueToken(),
            'login_count' => 3,
        ]);
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://motorclub-noord.example', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);

        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--dry-run' => true,
        ])
            ->expectsOutput('duplicates: 1')
            ->assertSuccessful();

        $this->assertSame(3, $legacy->fresh()->login_count);
        $this->assertDatabaseCount('growth_prospects', 0);
    }

    public function test_broad_community2026_records_are_not_pulled_in(): void
    {
        $this->seedCampaigns();
        [$csv, $markdown] = $this->writeMotorclubSources([
            $this->row('Motorclub Noord', 'https://motorclub-noord.example', 'info@motorclub-noord.example', 'Algemene motorclub', 'Motorclub', 'Club2026'),
        ]);
        $communityPath = storage_path('app/imports/community2026.csv');
        if (! is_dir(dirname($communityPath))) {
            mkdir(dirname($communityPath), 0777, true);
        }
        file_put_contents($communityPath, implode(PHP_EOL, [
            'name,website,email,prospect_type,prospect_subtype',
            'Camperclub Breed,https://camperclub-breed.example,info@camperclub-breed.example,community,camper_club',
        ]).PHP_EOL);

        $this->runWriteImport($csv, $markdown);

        $this->assertDatabaseHas('growth_prospects', ['name' => 'Motorclub Noord']);
        $this->assertDatabaseMissing('growth_prospects', ['name' => 'Camperclub Breed']);
    }

    public function test_club2026_bulk_preview_excludes_classic2026_records(): void
    {
        $this->seedCampaigns();
        $classic = GrowthProspect::factory()->create([
            'campaign_id' => GrowthCampaign::query()->where('slug', 'classic2026')->firstOrFail()->id,
            'last_campaign_slug' => 'classic2026',
            'email' => 'info@classic.example',
            'normalized_email' => 'info@classic.example',
            'partner_slug' => 'classic-club',
            'status' => GrowthProspect::LIFECYCLE_ENRICHED,
        ]);

        $data = GrowthProspectResource::club2026OutreachPreviewData(collect([$classic]));

        $this->assertSame(0, $data['sendableCount']);
    }

    private function seedCampaigns(): void
    {
        GrowthCampaign::query()->updateOrCreate(
            ['slug' => 'club2026'],
            ['name' => 'Club2026', 'status' => GrowthCampaign::STATUS_DRAFT],
        );
        GrowthCampaign::query()->updateOrCreate(
            ['slug' => 'classic2026'],
            ['name' => 'Classic2026', 'status' => GrowthCampaign::STATUS_DRAFT],
        );
    }

    private function runWriteImport(string $csv, string $markdown): void
    {
        $this->artisan('garagebook:growth:import-motorclubs', [
            '--file' => $csv,
            '--markdown' => $markdown,
            '--force' => true,
        ])
            ->expectsConfirmation('Dit schrijft motorclubprospects naar de lokale database. Er wordt niets gemaild. Doorgaan?', 'yes')
            ->assertSuccessful();
    }

    /**
     * @return array{name:string,website:string,email:string,category:string,subcategory:string,campaign:string}
     */
    private function row(string $name, string $website, string $email, string $category, string $subcategory, string $campaign): array
    {
        return compact('name', 'website', 'email', 'category', 'subcategory', 'campaign');
    }

    /**
     * @param  array<int, array{name:string,website:string,email:string,category:string,subcategory:string,campaign:string}>  $rows
     * @return array{string, string}
     */
    private function writeMotorclubSources(array $rows): array
    {
        $base = storage_path('framework/testing/motorclubs-'.uniqid());
        if (! is_dir($base)) {
            mkdir($base, 0777, true);
        }

        $csv = $base.'/motorclubs.csv';
        $handle = fopen($csv, 'w');
        fputcsv($handle, ['name', 'website', 'category', 'region', 'contact_name', 'email', 'priority', 'warmth', 'score', 'status', 'notes', 'partner_slug']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['name'],
                $row['website'],
                'Motorclub',
                'Landelijk',
                '',
                $row['email'],
                'A',
                'Warm',
                '9',
                'new',
                'Waarom interessant: test',
                str($row['name'])->slug()->value(),
            ]);
        }
        fclose($handle);

        $markdown = $base.'/motorclubs.md';
        $markdownRows = [
            '# Motorclubs',
            '',
            '| Naam | Website | Categorie | Subcategorie | Regio | Geschat bereik | Nieuwsbrief (ja/nee/onbekend) | Primair contactkanaal | Contactpersoon | E-mailadres | Organiseert evenementen? (ja/nee/onbekend) | Eigen magazine? (ja/nee/onbekend) | Facebook aanwezig? (ja/nee/onbekend) | Instagram aanwezig? (ja/nee/onbekend) | Prioriteit (A/B/C) | Warmte (Warm/Lauw/Koud) | Kansscore (1-10) | Campagne | Waarom interessant | Benaderstrategie | Status | Opmerkingen |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($rows as $row) {
            $markdownRows[] = '| '.$row['name'].' | '.$row['website'].' | '.$row['category'].' | '.$row['subcategory'].' | Landelijk | onbekend | ja | Algemeen contact | onbekend | '.($row['email'] ?: 'onbekend').' | ja | onbekend | ja | nee | A | Warm | 9 | '.$row['campaign'].' | Test relevantie | Test benadering | Nog niet benaderd | Test opmerkingen |';
        }

        file_put_contents($markdown, implode(PHP_EOL, $markdownRows).PHP_EOL);

        return [$csv, $markdown];
    }
}
