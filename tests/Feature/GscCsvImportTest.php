<?php

namespace Tests\Feature;

use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Services\Gsc\GscPageTypeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GscCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_gsc_pages_csv_with_dutch_columns(): void
    {
        $path = $this->writeCsv('gsc-pages.csv', [
            ['Pagina', 'Klikken', 'Vertoningen', 'CTR', 'Positie'],
            ['https://app.garagebook.nl/garage/2021-kawasaki-z650', '12', '300', '4,00%', '11,2'],
            ['https://app.garagebook.nl/contact', '1', '20', '0.5%', '5.4'],
        ]);

        $this->artisan('garagebook:gsc:import-csv', [
            '--pages' => $path,
            '--date' => '2026-07-08',
        ])
            ->expectsOutput('Page rows imported: 2')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gsc_page_snapshots', [
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 12,
            'impressions' => 300,
            'page_type' => 'garage_page',
        ]);

        $this->assertSame(0.04, (float) GscPageSnapshot::query()->where('path', '/garage/2021-kawasaki-z650')->value('ctr'));
    }

    public function test_imports_gsc_queries_csv_and_parses_ctr_with_dot(): void
    {
        $path = $this->writeCsv('gsc-queries.csv', [
            ['Zoekopdracht', 'Pagina', 'Klikken', 'Vertoningen', 'CTR', 'Positie'],
            ['kawasaki z650 onderhoud', 'https://app.garagebook.nl/garage/2021-kawasaki-z650', '3', '120', '2.5%', '9.8'],
            ['onderhoudsboekje kwijt', '', '0', '80', '0,00%', '14,1'],
        ]);

        $this->artisan('garagebook:gsc:import-csv', [
            '--queries' => $path,
            '--date' => '2026-07-08',
        ])
            ->expectsOutput('Query rows imported: 2')
            ->assertExitCode(0);

        $this->assertDatabaseHas('gsc_query_snapshots', [
            'query' => 'kawasaki z650 onderhoud',
            'path' => '/garage/2021-kawasaki-z650',
            'page_type' => 'garage_page',
        ]);

        $this->assertSame(0.025, (float) GscQuerySnapshot::query()->where('query', 'kawasaki z650 onderhoud')->value('ctr'));
        $this->assertNull(GscQuerySnapshot::query()->where('query', 'onderhoudsboekje kwijt')->value('path'));
    }

    public function test_page_type_detection(): void
    {
        $detector = app(GscPageTypeDetector::class);

        $this->assertSame('homepage', $detector->detect('https://app.garagebook.nl/'));
        $this->assertSame('garage_page', $detector->detect('/garage/2021-kawasaki-z650'));
        $this->assertSame('vehicle_authority', $detector->detect('/onderhoud/yamaha/mt-07'));
        $this->assertSame('seo_page', $detector->detect('/onderhoudsboekje-kwijt'));
        $this->assertSame('static_page', $detector->detect('/contact'));
        $this->assertSame('other', $detector->detect('/random'));
    }

    private function writeCsv(string $filename, array $rows): string
    {
        $path = storage_path('app/testing/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);

        return $path;
    }
}
