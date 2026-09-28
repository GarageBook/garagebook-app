<?php

namespace Tests\Feature;

use App\Models\WeeklyReportSnapshot;
use App\Services\Growth\WeeklySnapshotParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ImportWeeklySnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_parser_recognizes_metrics_versions_and_unknown_lines(): void
    {
        $parsed = app(WeeklySnapshotParser::class)->parse(<<<'TEXT'
Totaal gebruikers: 303
Registratie → voertuig: 70,0% (212/303)
Onbekende metriek: 12
losse toelichting
TEXT);

        $this->assertCount(2, $parsed['metrics']);
        $this->assertSame('activation-v1', $parsed['metrics'][0]['definition_version']);
        $this->assertSame(212, $parsed['metrics'][1]['numerator']);
        $this->assertSame(303, $parsed['metrics'][1]['denominator']);
        $this->assertSame('parsed_with_warnings', $parsed['status']);
        $this->assertCount(2, $parsed['warnings']);
    }

    public function test_command_previews_confirms_and_stores_raw_aggregate_snapshot(): void
    {
        $path = $this->snapshotFile();

        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $path, '--date' => '2026-08-31'])
            ->expectsOutputToContain('Preview weekly snapshot 2026-08-31')
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'yes')
            ->expectsOutput('Weekly snapshot opgeslagen.')
            ->assertSuccessful();

        $snapshot = WeeklyReportSnapshot::query()->with('metrics')->firstOrFail();
        $this->assertSame('2026-08-31', $snapshot->report_date->toDateString());
        $this->assertSame('file', $snapshot->source_type);
        $this->assertSame(WeeklySnapshotParser::PARSER_VERSION, $snapshot->parser_version);
        $this->assertSame(WeeklySnapshotParser::DEFINITION_VERSION, $snapshot->definition_version);
        $this->assertCount(2, $snapshot->metrics);
        $this->assertStringContainsString('Totaal gebruikers', $snapshot->raw_text);
    }

    public function test_duplicate_snapshot_is_never_silently_overwritten(): void
    {
        $path = $this->snapshotFile();

        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $path])
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'yes')
            ->assertSuccessful();

        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $path])
            ->expectsOutput('Dit rapport is al geïmporteerd; bestaande snapshot wordt niet overschreven.')
            ->assertFailed();

        $this->assertDatabaseCount('weekly_report_snapshots', 1);
    }

    public function test_same_date_with_different_text_is_stored_as_a_separate_snapshot(): void
    {
        $first = $this->snapshotFile("Totaal gebruikers: 303\n");
        $second = $this->snapshotFile("Totaal gebruikers: 304\n");

        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $first, '--date' => '2026-08-31'])
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'yes')
            ->assertSuccessful();
        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $second, '--date' => '2026-08-31'])
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseCount('weekly_report_snapshots', 2);
    }

    public function test_missing_date_defaults_to_current_amsterdam_date(): void
    {
        Carbon::setTestNow('2026-08-30 22:30:00 UTC');
        $path = $this->snapshotFile();

        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $path])
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'yes')
            ->assertSuccessful();

        $this->assertSame('2026-08-31', WeeklyReportSnapshot::query()->firstOrFail()->report_date->toDateString());
    }

    public function test_interactive_multiline_input_can_be_previewed_confirmed_and_stored(): void
    {
        $this->artisan('garagebook:import-weekly-snapshot', ['--date' => '2026-08-31'])
            ->expectsQuestion('Rapportregel (lege regel = klaar)', 'Totaal gebruikers: 303')
            ->expectsQuestion('Rapportregel (lege regel = klaar)', 'Registratie → voertuig: 70,0% (212/303)')
            ->expectsQuestion('Rapportregel (lege regel = klaar)', '')
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseCount('weekly_report_snapshots', 1);
        $this->assertDatabaseHas('weekly_report_snapshots', ['source_type' => 'stdin']);
    }

    public function test_cancel_after_preview_stores_nothing(): void
    {
        $path = $this->snapshotFile();

        $this->artisan('garagebook:import-weekly-snapshot', ['file' => $path])
            ->expectsConfirmation('Snapshot met deze metrics opslaan?', 'no')
            ->expectsOutput('Import geannuleerd; niets opgeslagen.')
            ->assertSuccessful();

        $this->assertDatabaseCount('weekly_report_snapshots', 0);
    }

    public function test_malformed_percentage_is_rejected_and_mismatch_is_warned(): void
    {
        $parsed = app(WeeklySnapshotParser::class)->parse(<<<'TEXT'
Registratie → voertuig: 70,0 (212/303)
Registratie → eerste onderhoudslog: 99,0% (113/303)
TEXT);

        $this->assertCount(1, $parsed['metrics']);
        $this->assertCount(2, $parsed['warnings']);
        $this->assertStringContainsString('geen geldig percentage', $parsed['warnings'][0]);
        $this->assertStringContainsString('past niet bij teller/noemer', $parsed['warnings'][1]);
    }

    private function snapshotFile(?string $contents = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'garagebook-weekly-');
        file_put_contents($path, $contents ?? "Totaal gebruikers: 303\nRegistratie → voertuig: 70,0% (212/303)\n");
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
