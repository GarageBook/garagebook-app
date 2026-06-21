<?php

namespace Tests\Feature;

use App\Models\OutreachCampaign;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OutreachMasterSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_prospect_from_csv_is_created(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $path = $this->writeMasterCsv('master-create.csv', [
            $this->row('Moto Breda', 'info@motobreda.nl', 'https://motobreda.nl', 'Breda'),
            ...$this->fillerRows(49, 'create'),
        ]);

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--force' => true,
        ])->assertSuccessful();

        $prospect = OutreachProspect::query()->where('email', 'info@motobreda.nl')->firstOrFail();

        $this->assertSame($campaign->id, $prospect->outreach_campaign_id);
        $this->assertSame('Moto Breda', $prospect->company_name);
        $this->assertSame('Breda', $prospect->city);
        $this->assertNotNull($prospect->token);
    }

    public function test_existing_prospect_matched_on_email_is_updated_but_token_and_logs_stay_unchanged(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $existing = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Oude Naam',
            'email' => 'INFO@MOTOBREDA.NL',
            'website' => 'https://oude-site.nl',
            'city' => 'Oud',
            'token' => 'existing-token',
            'created_at' => now()->subYear(),
        ]);
        $createdAt = $existing->created_at?->toDateTimeString();

        $log = $this->createOutreachLog($campaign, $existing, OutreachEmailLog::STATUS_SENT);

        $path = $this->writeMasterCsv('master-update.csv', [
            $this->row('Nieuwe Naam', 'info@motobreda.nl', 'https://motobreda.nl', 'Breda'),
            ...$this->fillerRows(49, 'update'),
        ]);

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--force' => true,
        ])->assertSuccessful();

        $fresh = $existing->fresh();

        $this->assertSame('Nieuwe Naam', $fresh->company_name);
        $this->assertSame('info@motobreda.nl', $fresh->email);
        $this->assertSame('https://motobreda.nl', $fresh->website);
        $this->assertSame('Breda', $fresh->city);
        $this->assertSame('existing-token', $fresh->token);
        $this->assertSame($createdAt, $fresh->created_at?->toDateTimeString());
        $this->assertDatabaseHas('outreach_email_logs', [
            'id' => $log->id,
            'outreach_prospect_id' => $existing->id,
            'status' => OutreachEmailLog::STATUS_SENT,
        ]);
    }

    public function test_existing_sent_log_stays_preserved(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $prospect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'email' => 'info@sent.nl',
        ]);
        $sentLog = $this->createOutreachLog($campaign, $prospect, OutreachEmailLog::STATUS_SENT);

        $path = $this->writeMasterCsv('master-sent-log.csv', [
            $this->row('Sent Prospect', 'info@sent.nl', 'https://sent.nl', 'Utrecht'),
            ...$this->fillerRows(49, 'sent'),
        ]);

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, OutreachEmailLog::query()->whereKey($sentLog->id)->count());
        $this->assertDatabaseHas('outreach_email_logs', [
            'id' => $sentLog->id,
            'status' => OutreachEmailLog::STATUS_SENT,
        ]);
    }

    public function test_website_conflict_goes_to_review_and_is_not_overwritten(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $existing = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Moto Conflict',
            'email' => 'old@motoconflict.nl',
            'website' => 'https://www.motoconflict.nl',
            'city' => 'Oud',
        ]);

        $path = $this->writeMasterCsv('master-website-conflict.csv', [
            $this->row('Moto Conflict Nieuw', 'new@motoconflict.nl', 'https://motoconflict.nl', 'Nieuw'),
            ...$this->fillerRows(49, 'conflict'),
        ]);

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--force' => true,
        ])->assertSuccessful();

        $fresh = $existing->fresh();

        $this->assertSame('old@motoconflict.nl', $fresh->email);
        $this->assertSame('Moto Conflict', $fresh->company_name);
        $this->assertSame('Oud', $fresh->city);
        $this->assertStringContainsString('Website matcht, maar email wijkt af', File::get(storage_path('app/outreach-sync-reports/sync_to_review.csv')));
    }

    public function test_default_sync_does_not_archive_live_prospect_not_in_csv(): void
    {
        $targetCampaign = OutreachCampaign::factory()->create(['slug' => 'target-campaign']);
        $otherCampaign = OutreachCampaign::factory()->create(['slug' => 'other-campaign']);
        $targetProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $targetCampaign->id,
            'email' => 'old-target@example.nl',
        ]);
        $otherProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $otherCampaign->id,
            'email' => 'old-other@example.nl',
        ]);

        $path = $this->writeMasterCsv('master-archive.csv', $this->fillerRows(50, 'archive'));

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $targetCampaign->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertNull($targetProspect->fresh()->archived_at);
        $this->assertNull($otherProspect->fresh()->archived_at);
        $this->assertStringContainsString('Actieve prospect in gekozen campaign staat niet meer in CSV', File::get(storage_path('app/outreach-sync-reports/sync_to_archive.csv')));
    }

    public function test_sync_with_archive_missing_archives_campaign_scoped_missing_prospects(): void
    {
        $targetCampaign = OutreachCampaign::factory()->create(['slug' => 'target-campaign']);
        $otherCampaign = OutreachCampaign::factory()->create(['slug' => 'other-campaign']);
        $targetProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $targetCampaign->id,
            'email' => 'old-target@example.nl',
        ]);
        $otherProspect = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $otherCampaign->id,
            'email' => 'old-other@example.nl',
        ]);

        $path = $this->writeMasterCsv('master-archive-opt-in.csv', $this->fillerRows(50, 'archive-opt-in'));

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $targetCampaign->slug,
            '--archive-missing' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertNotNull($targetProspect->fresh()->archived_at);
        $this->assertNull($otherProspect->fresh()->archived_at);
    }

    public function test_dry_run_changes_no_database_rows(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $existing = OutreachProspect::factory()->create([
            'outreach_campaign_id' => $campaign->id,
            'company_name' => 'Dry Existing',
            'email' => 'dry@example.nl',
            'city' => 'Oud',
        ]);
        $countBefore = OutreachProspect::query()->count();

        $path = $this->writeMasterCsv('master-dry-run.csv', [
            $this->row('Dry Updated', 'dry@example.nl', 'https://dry.nl', 'Nieuw'),
            ...$this->fillerRows(49, 'dry'),
        ]);

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--dry-run' => true,
        ])->assertSuccessful();

        $fresh = $existing->fresh();

        $this->assertSame($countBefore, OutreachProspect::query()->count());
        $this->assertSame('Dry Existing', $fresh->company_name);
        $this->assertSame('Oud', $fresh->city);
        $this->assertNull($fresh->archived_at);
    }

    public function test_csv_without_email_is_skipped(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $path = $this->writeMasterCsv('master-skipped.csv', [
            $this->row('Geen Email', '', 'https://geen-email.nl', 'Breda'),
            ...$this->fillerRows(50, 'skipped'),
        ]);

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(0, OutreachProspect::query()->where('company_name', 'Geen Email')->count());
        $this->assertStringContainsString('CSV-regel heeft geen email', File::get(storage_path('app/outreach-sync-reports/sync_skipped.csv')));
    }

    public function test_csv_with_less_than_fifty_valid_email_rows_stops_with_error(): void
    {
        $campaign = OutreachCampaign::factory()->create(['slug' => 'master-campaign']);
        $path = $this->writeMasterCsv('master-too-small.csv', $this->fillerRows(49, 'small'));

        $this->artisan('garagebook:sync-outreach-master', [
            'csv_path' => $path,
            '--campaign' => $campaign->slug,
            '--force' => true,
        ])->assertFailed();

        $this->assertSame(0, OutreachProspect::query()->count());
    }

    private function createOutreachLog(OutreachCampaign $campaign, OutreachProspect $prospect, string $status): OutreachEmailLog
    {
        return OutreachEmailLog::query()->create([
            'outreach_campaign_id' => $campaign->id,
            'outreach_prospect_id' => $prospect->id,
            'to_email' => $prospect->email,
            'subject' => 'Outreach',
            'body_snapshot' => 'Body',
            'status' => $status,
            'sent_at' => $status === OutreachEmailLog::STATUS_SENT ? now() : null,
        ]);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function writeMasterCsv(string $filename, array $rows): string
    {
        $path = storage_path('app/outreach/'.$filename);
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'w');

        fputcsv($handle, [
            'company_name',
            'email',
            'website',
            'phone',
            'city',
            'province',
            'postal_code',
            'country',
            'source',
            'import_note',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['company_name'],
                $row['email'],
                $row['website'],
                $row['phone'],
                $row['city'],
                $row['province'],
                $row['postal_code'],
                $row['country'],
                $row['source'],
                $row['import_note'],
            ]);
        }

        fclose($handle);

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function row(string $companyName, string $email, string $website, string $city): array
    {
        return [
            'company_name' => $companyName,
            'email' => $email,
            'website' => $website,
            'phone' => '010-1234567',
            'city' => $city,
            'province' => 'Noord-Brabant',
            'postal_code' => '4811 AA',
            'country' => 'Nederland',
            'source' => 'test',
            'import_note' => 'Master sync test',
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function fillerRows(int $count, string $prefix): array
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $this->row(
                'Filler '.$prefix.' '.$i,
                'filler-'.$prefix.'-'.$i.'@example.nl',
                'https://filler-'.$prefix.'-'.$i.'.example.nl',
                'Teststad'
            );
        }

        return $rows;
    }
}
