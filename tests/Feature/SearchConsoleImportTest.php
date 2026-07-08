<?php

namespace Tests\Feature;

use App\Filament\Pages\SearchConsoleImport;
use App\Models\GscPageSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SearchConsoleImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_console_import_page_is_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/search-console-import')
            ->assertOk()
            ->assertSeeText('Search Console Import')
            ->assertSeeText('CSV import');

        $this->actingAs($user)
            ->get('/admin/search-console-import')
            ->assertForbidden();
    }

    public function test_admin_can_upload_and_import_gsc_csv_exports(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '2026-07-08')
            ->set('pagesCsv', $this->pagesCsv())
            ->set('queriesCsv', $this->queriesCsv())
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result.status', 'completed')
            ->assertSet('result.pages', 1)
            ->assertSet('result.queries', 1)
            ->assertNotified('Search Console import afgerond');

        $this->assertDatabaseHas('gsc_page_snapshots', [
            'date' => '2026-07-08 00:00:00',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 12,
            'impressions' => 300,
            'page_type' => 'garage_page',
        ]);

        $this->assertDatabaseHas('gsc_query_snapshots', [
            'date' => '2026-07-08 00:00:00',
            'query' => 'kawasaki z650 onderhoud',
            'path' => '/garage/2021-kawasaki-z650',
            'page_type' => 'garage_page',
        ]);

        $this->assertDatabaseHas('gsc_import_logs', [
            'date' => '2026-07-08 00:00:00',
            'pages_imported' => 1,
            'queries_imported' => 1,
            'user_id' => $admin->id,
            'status' => 'completed',
        ]);
    }

    public function test_import_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '')
            ->call('import')
            ->assertHasErrors([
                'date' => 'required',
            ]);
    }

    public function test_import_rejects_wrong_file_types(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $wrongFile = UploadedFile::fake()->createWithContent('gsc-pages.json', '{"Pagina":"/"}');

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '2026-07-08')
            ->set('pagesCsv', $wrongFile)
            ->set('queriesCsv', $this->queriesCsv())
            ->call('import')
            ->assertHasErrors(['pagesCsv' => 'mimes']);
    }

    public function test_duplicate_import_is_skipped_without_overwrite(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/existing',
            'path' => '/existing',
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 4,
            'page_type' => 'other',
        ]);

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '2026-07-08')
            ->set('pagesCsv', $this->pagesCsv())
            ->set('queriesCsv', $this->queriesCsv())
            ->call('import')
            ->assertSet('result.status', 'failed')
            ->assertNotified('Search Console import afgerond');

        $this->assertDatabaseHas('gsc_page_snapshots', [
            'path' => '/existing',
        ]);

        $this->assertDatabaseMissing('gsc_page_snapshots', [
            'path' => '/garage/2021-kawasaki-z650',
        ]);

        $this->assertDatabaseHas('gsc_import_logs', [
            'date' => '2026-07-08 00:00:00',
            'status' => 'failed',
        ]);
    }

    public function test_duplicate_import_can_replace_existing_snapshots(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/existing',
            'path' => '/existing',
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 4,
            'page_type' => 'other',
        ]);

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '2026-07-08')
            ->set('overwrite', 'yes')
            ->set('pagesCsv', $this->pagesCsv())
            ->set('queriesCsv', $this->queriesCsv())
            ->call('import')
            ->assertSet('result.status', 'completed')
            ->assertSet('result.warnings', [])
            ->assertSet('result.pages', 1);

        $this->assertDatabaseMissing('gsc_page_snapshots', [
            'path' => '/existing',
        ]);

        $this->assertDatabaseHas('gsc_page_snapshots', [
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 12,
        ]);
    }

    public function test_successful_import_flushes_dashboard_cache(): void
    {
        Storage::fake('local');
        Cache::shouldReceive('flush')->once()->andReturnTrue();

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SearchConsoleImport::class)
            ->set('date', '2026-07-08')
            ->set('pagesCsv', $this->pagesCsv())
            ->set('queriesCsv', $this->queriesCsv())
            ->call('import')
            ->assertSet('result.status', 'completed');
    }

    private function pagesCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('gsc-pages.csv', implode("\n", [
            'Pagina;Klikken;Vertoningen;CTR;Positie',
            'https://app.garagebook.nl/garage/2021-kawasaki-z650;12;300;4,00%;11,2',
        ]));
    }

    private function queriesCsv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('gsc-queries.csv', implode("\n", [
            'Zoekopdracht;Pagina;Klikken;Vertoningen;CTR;Positie',
            'kawasaki z650 onderhoud;https://app.garagebook.nl/garage/2021-kawasaki-z650;3;120;2,5%;9,8',
        ]));
    }
}
