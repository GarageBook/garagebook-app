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

    public function test_detector_recognizes_standard_dutch_gsc_exports_from_headers(): void
    {
        $detector = app(GscCsvTypeDetector::class);

        $cases = [
            "Pagina's.csv" => GscCsvTypeDetector::PAGES,
            'Zoekopdrachten.csv' => GscCsvTypeDetector::QUERIES,
            'Landen.csv' => GscCsvTypeDetector::COUNTRIES,
            'Apparaten.csv' => GscCsvTypeDetector::DEVICES,
            'Zoekopmaak.csv' => GscCsvTypeDetector::SEARCH_APPEARANCE,
            'Diagram.csv' => GscCsvTypeDetector::DATES,
        ];

        foreach ($cases as $filename => $expectedType) {
            $this->assertSame($expectedType, $detector->detect($this->fixturePath($filename), 'Export.csv'));
        }
    }

    public function test_bulk_import_uses_standard_dutch_gsc_fixture_headers(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            ['path' => $this->fixturePath("Pagina's.csv"), 'name' => "Pagina's.csv"],
            ['path' => $this->fixturePath('Zoekopdrachten.csv'), 'name' => 'Zoekopdrachten.csv'],
            ['path' => $this->fixturePath('Landen.csv'), 'name' => 'Landen.csv'],
            ['path' => $this->fixturePath('Apparaten.csv'), 'name' => 'Apparaten.csv'],
            ['path' => $this->fixturePath('Zoekopmaak.csv'), 'name' => 'Zoekopmaak.csv'],
            ['path' => $this->fixturePath('Diagram.csv'), 'name' => 'Diagram.csv'],
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertSame(6, $summary['processed_files']);
        $this->assertSame(0, $summary['skipped_files']);
        $this->assertSame(1, GscPageSnapshot::query()->count());
        $this->assertSame(1, GscQuerySnapshot::query()->count());
        $this->assertSame(1, GscCountrySnapshot::query()->count());
        $this->assertSame(1, GscDeviceSnapshot::query()->count());
        $this->assertSame(1, GscSearchAppearanceSnapshot::query()->count());
        $this->assertSame(1, GscDateSnapshot::query()->count());
    }

    public function test_unknown_bulk_import_warning_includes_detected_headers(): void
    {
        $path = $this->writeCsv('unknown-warning.csv', ['Pagina;Klikken;Vertoningen;CTR', 'https://app.garagebook.nl/garage/a;1;2;50%']);

        $summary = app(GscCsvImportService::class)->importBulkSession([
            ['path' => $path, 'name' => "Pagina's.csv"],
        ], '2026-07-08');

        $this->assertSame('completed_with_warnings', $summary['status']);
        $this->assertSame(1, $summary['skipped_files']);
        $this->assertStringContainsString("Bestand: Pagina's.csv", $summary['warnings'][0]);
        $this->assertStringContainsString('Headers: pagina, klikken, vertoningen, ctr', $summary['warnings'][0]);
        $this->assertStringContainsString('Herkende dimensiekandidaten: pages', $summary['warnings'][0]);
        $this->assertStringContainsString('Herkende metriekandidaten: clicks, impressions, ctr', $summary['warnings'][0]);
        $this->assertStringContainsString('Ontbrekende vereiste velden: position', $summary['warnings'][0]);
        $this->assertStringContainsString('Waarom onbekend', $summary['warnings'][0]);
    }

    public function test_filters_only_notice_does_not_mark_session_as_warning(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            ['path' => $this->writeCsv('filters.csv', ['Filter,Waarde', 'Land,Nederland']), 'name' => 'Filters.csv'],
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertSame([], $summary['warnings']);
        $this->assertSame([], $summary['errors']);
        $this->assertSame(1, $summary['skipped_files']);
        $this->assertSame(1, $summary['intentionally_skipped_files']);
        $this->assertStringContainsString('bewust overgeslagen (filters)', implode("\n", $summary['notices']));
    }

    public function test_compare_notice_does_not_mark_session_as_warning(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('compare-devices-notice.csv', [
                $this->compareHeader('apparaat'),
                'MOBILE,12,2,300,50,4%,4%,11.2,20.4',
            ]),
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertSame([], $summary['warnings']);
        $this->assertSame([], $summary['errors']);
        $this->assertStringContainsString('Vergelijkingskolommen gedetecteerd; alleen nieuwste periode geïmporteerd.', implode("\n", $summary['notices']));
    }

    public function test_import_exception_marks_session_as_failed(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('bad-date.csv', [
                'datum,aantal klikken,vertoningen,ctr,positie',
                'geen-datum,1,2,50%,1',
            ]),
        ], '2026-07-08');

        $this->assertSame('failed', $summary['status']);
        $this->assertSame([], $summary['warnings']);
        $this->assertNotEmpty($summary['errors']);
        $this->assertStringContainsString('bad-date.csv', $summary['errors'][0]);
    }

    public function test_detects_search_appearance_with_aantal_klikken(): void
    {
        $this->assertDetected(GscCsvTypeDetector::SEARCH_APPEARANCE, 'zoekopmaak, aantal klikken, vertoningen, ctr, positie');
    }

    public function test_detects_devices_with_aantal_klikken(): void
    {
        $this->assertDetected(GscCsvTypeDetector::DEVICES, 'apparaat, aantal klikken, vertoningen, ctr, positie');
    }

    public function test_detects_countries_with_aantal_klikken(): void
    {
        $this->assertDetected(GscCsvTypeDetector::COUNTRIES, 'land, aantal klikken, vertoningen, ctr, positie');
    }

    public function test_detects_pages_with_toppaginas(): void
    {
        $this->assertDetected(GscCsvTypeDetector::PAGES, "toppagina's, aantal klikken, vertoningen, ctr, positie");
    }

    public function test_detects_queries_with_meest_uitgevoerde_zoekopdrachten(): void
    {
        $this->assertDetected(GscCsvTypeDetector::QUERIES, 'meest uitgevoerde zoekopdrachten, aantal klikken, vertoningen, ctr, positie');
    }

    public function test_detects_dates_with_datum(): void
    {
        $this->assertDetected(GscCsvTypeDetector::DATES, 'datum, aantal klikken, vertoningen, ctr, positie');
    }

    public function test_detects_compare_devices(): void
    {
        $this->assertDetected(GscCsvTypeDetector::DEVICES, $this->compareHeader('apparaat'));
    }

    public function test_detects_compare_countries(): void
    {
        $this->assertDetected(GscCsvTypeDetector::COUNTRIES, $this->compareHeader('land'));
    }

    public function test_detects_compare_pages(): void
    {
        $this->assertDetected(GscCsvTypeDetector::PAGES, $this->compareHeader("toppagina's"));
    }

    public function test_detects_compare_queries(): void
    {
        $this->assertDetected(GscCsvTypeDetector::QUERIES, $this->compareHeader('meest uitgevoerde zoekopdrachten'));
    }

    public function test_imports_normal_dutch_pages_export(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('normal-pages.csv', [
                "toppagina's, aantal klikken, vertoningen, ctr, positie",
                'https://app.garagebook.nl/garage/nl-export,12,300,4%,11.2',
            ]),
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertDatabaseHas('gsc_page_snapshots', [
            'path' => '/garage/nl-export',
            'clicks' => 12,
            'impressions' => 300,
        ]);
        $this->assertSame(0.04, (float) GscPageSnapshot::query()->where('path', '/garage/nl-export')->value('ctr'));
    }

    public function test_imports_compare_dutch_pages_export(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('compare-pages.csv', [
                $this->compareHeader("toppagina's"),
                'https://app.garagebook.nl/garage/compare-page,12,2,300,50,4%,4%,11.2,20.4',
            ]),
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertSame([], $summary['warnings']);
        $this->assertStringContainsString('Vergelijkingskolommen gedetecteerd; alleen nieuwste periode geïmporteerd.', implode("\n", $summary['notices']));
        $this->assertDatabaseHas('gsc_page_snapshots', [
            'path' => '/garage/compare-page',
            'clicks' => 12,
            'impressions' => 300,
        ]);
        $this->assertSame(11.2, (float) GscPageSnapshot::query()->where('path', '/garage/compare-page')->value('position'));
    }

    public function test_imports_normal_dutch_queries_export(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('normal-queries.csv', [
                'meest uitgevoerde zoekopdrachten, aantal klikken, vertoningen, ctr, positie',
                'garagebook onderhoud,7,140,5%,9.4',
            ]),
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertDatabaseHas('gsc_query_snapshots', [
            'query' => 'garagebook onderhoud',
            'clicks' => 7,
            'impressions' => 140,
        ]);
    }

    public function test_imports_compare_dutch_queries_export(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('compare-queries.csv', [
                $this->compareHeader('meest uitgevoerde zoekopdrachten'),
                'garagebook compare,9,1,180,20,5%,5%,8.1,16.2',
            ]),
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertSame([], $summary['warnings']);
        $this->assertStringContainsString('Vergelijkingskolommen gedetecteerd; alleen nieuwste periode geïmporteerd.', implode("\n", $summary['notices']));
        $this->assertDatabaseHas('gsc_query_snapshots', [
            'query' => 'garagebook compare',
            'clicks' => 9,
            'impressions' => 180,
        ]);
        $this->assertSame(8.1, (float) GscQuerySnapshot::query()->where('query', 'garagebook compare')->value('position'));
    }

    public function test_imports_devices_countries_search_appearance_dates(): void
    {
        $summary = app(GscCsvImportService::class)->importBulkSession([
            $this->writeCsv('normal-devices.csv', ['apparaat, aantal klikken, vertoningen, ctr, positie', 'MOBILE,2,100,2%,7.2']),
            $this->writeCsv('normal-countries.csv', ['land, aantal klikken, vertoningen, ctr, positie', 'Nederland,3,150,2%,8.3']),
            $this->writeCsv('normal-appearance.csv', ['zoekopmaak, aantal klikken, vertoningen, ctr, positie', 'Productresultaten,4,200,2%,9.4']),
            $this->writeCsv('normal-dates.csv', ['datum, aantal klikken, vertoningen, ctr, positie', '2026-07-01,5,250,2%,10.5']),
        ], '2026-07-08');

        $this->assertSame('completed', $summary['status']);
        $this->assertDatabaseHas('gsc_device_snapshots', ['device' => 'MOBILE', 'clicks' => 2, 'impressions' => 100]);
        $this->assertDatabaseHas('gsc_country_snapshots', ['country' => 'Nederland', 'clicks' => 3, 'impressions' => 150]);
        $this->assertDatabaseHas('gsc_search_appearance_snapshots', ['appearance' => 'Productresultaten', 'clicks' => 4, 'impressions' => 200]);
        $this->assertDatabaseHas('gsc_date_snapshots', ['data_date' => '2026-07-01 00:00:00', 'clicks' => 5, 'impressions' => 250]);
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
        $this->assertSame('completed', $replaced['status']);
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

    private function assertDetected(string $expectedType, string $header): void
    {
        $detector = app(GscCsvTypeDetector::class);

        $this->assertSame($expectedType, $detector->detect($this->writeCsv(md5($header).'.csv', [$header, $this->dummyRowForHeader($header)]), 'Export.csv'));
    }

    private function compareHeader(string $dimension): string
    {
        return $dimension.', 25 06 2026 01 07 2026 aantal klikken, 18 06 2026 24 06 2026 aantal klikken, 25 06 2026 01 07 2026 vertoningen, 18 06 2026 24 06 2026 vertoningen, 25 06 2026 01 07 2026 ctr, 18 06 2026 24 06 2026 ctr, 25 06 2026 01 07 2026 positie, 18 06 2026 24 06 2026 positie';
    }

    private function dummyRowForHeader(string $header): string
    {
        $columns = substr_count($header, ',') + 1;
        $values = array_fill(0, $columns, '1');
        $values[0] = str_contains($header, 'pagina') ? 'https://app.garagebook.nl/garage/dummy' : 'dummy';

        return implode(',', $values);
    }

    private function fixturePath(string $filename): string
    {
        return base_path('tests/Fixtures/gsc/'.$filename);
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
