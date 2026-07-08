<?php

namespace Tests\Feature;

use App\Filament\Pages\SearchConsoleImport;
use App\Models\GscCountrySnapshot;
use App\Models\GscDateSnapshot;
use App\Models\GscDeviceSnapshot;
use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Models\GscSearchAppearanceSnapshot;
use App\Models\User;
use App\Services\Gsc\GscCsvImportService;
use App\Services\Gsc\GscCsvTypeDetector;
use App\Services\Gsc\SeoOpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GscBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_detector_recognizes_supported_gsc_csv_types(): void
    {
        $detector = app(GscCsvTypeDetector::class);

        $this->assertSame('pages', $detector->detect($this->writeCsv('pages.csv', ['Pagina;Klikken;Vertoningen;CTR;Positie'])));
        $this->assertSame('queries', $detector->detect($this->writeCsv('queries.csv', ['Zoekopdracht;Klikken;Vertoningen;CTR;Positie'])));
        $this->assertSame('countries', $detector->detect($this->writeCsv('countries.csv', ['Land;Klikken;Vertoningen;CTR;Positie'])));
        $this->assertSame('devices', $detector->detect($this->writeCsv('devices.csv', ['Device,Clicks,Impressions,CTR,Position'])));
        $this->assertSame('search_appearance', $detector->detect($this->writeCsv('appearance.csv', ['Zoekopmaak;Klikken;Vertoningen;CTR;Positie'])));
        $this->assertSame('dates', $detector->detect($this->writeCsv('diagram.csv', ['Datum;Klikken;Vertoningen;CTR;Positie'])));
        $this->assertSame('unknown', $detector->detect($this->writeCsv('unknown.csv', ['Kolom;Waarde'])));
    }

    public function test_bulk_upload_imports_multiple_csv_types_and_skips_unknown_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '2026-07-08')
            ->set('csvFiles', [
                $this->uploadedCsv('pages.csv', ['Pagina;Klikken;Vertoningen;CTR;Positie', 'https://app.garagebook.nl/garage/a;10;200;5%;8']),
                $this->uploadedCsv('queries.csv', ['Zoekopdracht;Pagina;Klikken;Vertoningen;CTR;Positie', 'yamaha onderhoud;https://app.garagebook.nl/garage/a;3;100;3%;9']),
                $this->uploadedCsv('countries.csv', ['Land;Klikken;Vertoningen;CTR;Positie', 'Nederland;1;120;0,8%;11']),
                $this->uploadedCsv('devices.csv', ['Apparaat;Klikken;Vertoningen;CTR;Positie', 'MOBILE;1;150;0,6%;12']),
                $this->uploadedCsv('appearance.csv', ['Zoekopmaak;Klikken;Vertoningen;CTR;Positie', 'Web Light results;0;130;0%;10']),
                $this->uploadedCsv('diagram.csv', ['Datum;Klikken;Vertoningen;CTR;Positie', '2026-07-01;5;100;5%;8']),
                $this->uploadedCsv('unknown.csv', ['Kolom;Waarde', 'x;y']),
            ])
            ->call('import')
            ->assertSet('result.status', 'completed_with_warnings')
            ->assertSet('result.pages', 1)
            ->assertSet('result.queries', 1)
            ->assertSet('result.countries', 1)
            ->assertSet('result.devices', 1)
            ->assertSet('result.search_appearances', 1)
            ->assertSet('result.date_rows', 1)
            ->assertSet('result.skipped_files', 1);

        $this->assertDatabaseHas('gsc_import_sessions', [
            'import_date' => '2026-07-08 00:00:00',
            'status' => 'completed_with_warnings',
            'processed_files' => 6,
            'skipped_files' => 1,
        ]);
        $this->assertSame(1, GscPageSnapshot::query()->count());
        $this->assertSame(1, GscQuerySnapshot::query()->count());
        $this->assertSame(1, GscCountrySnapshot::query()->count());
        $this->assertSame(1, GscDeviceSnapshot::query()->count());
        $this->assertSame(1, GscSearchAppearanceSnapshot::query()->count());
        $this->assertSame(1, GscDateSnapshot::query()->count());
    }

    public function test_duplicate_bulk_import_is_blocked_without_replace_and_replace_clears_old_snapshots(): void
    {
        $path = $this->writeCsv('pages-replace.csv', ['Pagina;Klikken;Vertoningen;CTR;Positie', 'https://app.garagebook.nl/garage/new;9;100;9%;4']);

        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/garage/old',
            'path' => '/garage/old',
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 1,
            'page_type' => 'garage_page',
        ]);

        $blocked = app(GscCsvImportService::class)->importBulkSession([$path], '2026-07-08');
        $this->assertSame('failed', $blocked['status']);
        $this->assertDatabaseHas('gsc_page_snapshots', ['path' => '/garage/old']);
        $this->assertDatabaseMissing('gsc_page_snapshots', ['path' => '/garage/new']);

        $replaced = app(GscCsvImportService::class)->importBulkSession([$path], '2026-07-08', true);
        $this->assertSame('completed_with_warnings', $replaced['status']);
        $this->assertDatabaseMissing('gsc_page_snapshots', ['path' => '/garage/old']);
        $this->assertDatabaseHas('gsc_page_snapshots', ['path' => '/garage/new']);
    }

    public function test_dashboard_shows_imported_dimensions(): void
    {
        $admin = User::factory()->admin()->create();
        GscDeviceSnapshot::query()->create(['date' => '2026-07-08', 'device' => 'MOBILE', 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 10]);
        GscCountrySnapshot::query()->create(['date' => '2026-07-08', 'country' => 'Nederland', 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 10]);
        GscSearchAppearanceSnapshot::query()->create(['date' => '2026-07-08', 'appearance' => 'Web', 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 10]);
        GscDateSnapshot::query()->create(['date' => '2026-07-08', 'data_date' => '2026-07-01', 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 10]);

        $this->actingAs($admin)
            ->get('/admin/search-console-insights')
            ->assertOk()
            ->assertSeeText('Apparaten')
            ->assertSeeText('MOBILE')
            ->assertSeeText('Landen')
            ->assertSeeText('Nederland')
            ->assertSeeText('Zoekopmaak')
            ->assertSeeText('Datumtrend');
    }

    public function test_opportunity_engine_uses_dimension_signals(): void
    {
        GscDeviceSnapshot::query()->create(['date' => '2026-07-08', 'device' => 'MOBILE', 'clicks' => 1, 'impressions' => 150, 'ctr' => 0.005, 'position' => 12]);
        GscDeviceSnapshot::query()->create(['date' => '2026-07-08', 'device' => 'DESKTOP', 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 7]);
        GscCountrySnapshot::query()->create(['date' => '2026-07-08', 'country' => 'Nederland', 'clicks' => 1, 'impressions' => 200, 'ctr' => 0.005, 'position' => 11]);
        GscSearchAppearanceSnapshot::query()->create(['date' => '2026-07-08', 'appearance' => 'Product results', 'clicks' => 1, 'impressions' => 160, 'ctr' => 0.006, 'position' => 9]);
        foreach ([['2026-07-01', 10], ['2026-07-02', 9], ['2026-07-03', 2], ['2026-07-04', 1]] as [$date, $clicks]) {
            GscDateSnapshot::query()->create(['date' => '2026-07-08', 'data_date' => $date, 'clicks' => $clicks, 'impressions' => 100, 'ctr' => 0.01, 'position' => 10]);
        }

        $types = collect(app(SeoOpportunityService::class)->refreshLatest())->pluck('type')->all();

        $this->assertContains(SeoOpportunityService::TYPE_MOBILE_CTR, $types);
        $this->assertContains(SeoOpportunityService::TYPE_COUNTRY_LOW_CTR, $types);
        $this->assertContains(SeoOpportunityService::TYPE_SEARCH_APPEARANCE_LOW_CTR, $types);
        $this->assertContains(SeoOpportunityService::TYPE_DATE_TREND_DECLINE, $types);
    }

    public function test_import_folder_command_uses_bulk_session_flow(): void
    {
        $directory = storage_path('app/testing/gsc-folder');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($directory.'/pages.csv', implode("\n", ['Pagina;Klikken;Vertoningen;CTR;Positie', 'https://app.garagebook.nl/garage/folder;4;80;5%;6']));
        file_put_contents($directory.'/devices.csv', implode("\n", ['Apparaat;Klikken;Vertoningen;CTR;Positie', 'DESKTOP;4;80;5%;6']));

        $this->artisan('garagebook:gsc:import-folder', [
            '--path' => $directory,
            '--date' => '2026-07-08',
        ])
            ->expectsOutput('Page rows imported: 1')
            ->expectsOutput('Device rows imported: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gsc_import_sessions', [
            'processed_files' => 2,
            'pages_imported' => 1,
            'devices_imported' => 1,
        ]);
    }

    private function uploadedCsv(string $name, array $lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, implode("\n", $lines));
    }

    private function writeCsv(string $name, array $lines): string
    {
        $path = storage_path('app/testing/'.$name);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }
}
