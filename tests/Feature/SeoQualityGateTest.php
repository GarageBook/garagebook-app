<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoQualityGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_audit_passes_for_valid_public_garage_fixture(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');

        $vehicle = $this->createIndexableVehicle();
        $this->createNoindexVehicle();
        $this->createOutreachVehicle();

        $this->artisan('garagebook:seo-audit')
            ->expectsOutputToContain('=== SITEMAP ===')
            ->expectsOutputToContain('✓ sitemap bestaat: /sitemap.xml')
            ->expectsOutputToContain('✓ sitemap bestaat: /sitemap-garages.xml')
            ->expectsOutputToContain('✓ alle URLs geven 200')
            ->expectsOutputToContain('✓ geen redirects')
            ->expectsOutputToContain('✓ geen duplicates')
            ->expectsOutputToContain('✓ geen querystrings')
            ->expectsOutputToContain("✓ geen noindex pagina's")
            ->expectsOutputToContain('✓ geen demo/outreach URLs')
            ->expectsOutputToContain('=== GARAGE PAGES ===')
            ->expectsOutputToContain('✓ self canonical')
            ->expectsOutputToContain('✓ canonical = sitemap URL')
            ->expectsOutputToContain('✓ WebPage schema aanwezig')
            ->expectsOutputToContain('✓ Vehicle schema aanwezig')
            ->expectsOutputToContain('✓ GEEN Product schema')
            ->expectsOutputToContain('✓ title aanwezig')
            ->expectsOutputToContain('✓ meta description aanwezig')
            ->expectsOutputToContain('✓ H1 aanwezig')
            ->expectsOutputToContain('=== REDIRECTS ===')
            ->expectsOutputToContain('✓ exact 1 redirect max')
            ->expectsOutputToContain('✓ geen redirect chains')
            ->expectsOutputToContain('✓ geen http canonical')
            ->expectsOutputToContain('✓ geen www canonical')
            ->expectsOutputToContain('=== INDEXABILITY ===')
            ->expectsOutputToContain('✓ shouldIndex consistent met sitemap')
            ->expectsOutputToContain('✓ shouldIndex consistent met robots')
            ->expectsOutputToContain('✓ shouldIndex consistent met canonical')
            ->expectsOutputToContain('PASS')
            ->assertExitCode(0);

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertSee('https://app.garagebook.nl/garage/'.$vehicle->public_slug, false)
            ->assertDontSee('garage-demo', false);
    }

    public function test_seo_audit_ignores_http_app_url_for_public_garage_canonical(): void
    {
        Config::set('app.url', 'http://app.garagebook.nl');
        $this->createIndexableVehicle();

        $this->artisan('garagebook:seo-audit')
            ->expectsOutputToContain('✓ geen http canonical')
            ->expectsOutputToContain('PASS')
            ->assertExitCode(0);
    }

    public function test_garage_page_regressions_are_covered_by_fixture(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        $vehicle = $this->createIndexableVehicle();

        $response = $this->get('/garage/'.$vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://app.garagebook.nl/garage/'.$vehicle->public_slug.'">', false);
        $response->assertSee('<meta name="robots" content="index,follow">', false);
        $response->assertSee('"@type": "WebPage"', false);
        $response->assertSee('"@type": "Vehicle"', false);
        $response->assertDontSee('"@type": "Product"', false);
        $response->assertSee('<h1', false);
        $response->assertSee('<meta name="description" content="', false);
        $response->assertSee('<title>', false);
    }

    public function test_noindex_demo_and_outreach_vehicles_stay_out_of_sitemap(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        $hiddenVehicle = $this->createNoindexVehicle();
        $outreachVehicle = $this->createOutreachVehicle();

        $this->get('/garage/'.$hiddenVehicle->public_slug)
            ->assertNotFound();

        $this->get('/garage/'.$outreachVehicle->public_slug)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertDontSee($hiddenVehicle->public_slug, false)
            ->assertDontSee($outreachVehicle->public_slug, false);
    }

    public function test_garage_querystring_has_single_clean_redirect(): void
    {
        Config::set('app.url', 'https://app.garagebook.nl');
        $vehicle = $this->createIndexableVehicle();

        $this->get('/garage/'.$vehicle->public_slug.'?utm_source=test')
            ->assertStatus(301)
            ->assertRedirect('https://app.garagebook.nl/garage/'.$vehicle->public_slug);
    }

    public function test_seo_report_writes_markdown_report(): void
    {
        if (! array_key_exists('garagebook:seo-report', app(Kernel::class)->all())) {
            $this->markTestSkipped('garagebook:seo-report command is not registered in this checkout.');
        }
        Storage::fake('local');
        Config::set('app.url', 'https://app.garagebook.nl');
        $this->createIndexableVehicle();

        $this->artisan('garagebook:seo-report', ['--filename' => 'seo-report-test.md'])
            ->expectsOutput('SEO report written to storage/app/reports/seo-report-test.md')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('reports/seo-report-test.md');
        $report = Storage::disk('local')->get('reports/seo-report-test.md');

        $this->assertStringContainsString('# GarageBook SEO Report', $report);
        $this->assertStringContainsString('Indexeerbare garagepagina\'s: 1', $report);
        $this->assertStringContainsString('Structured data status', $report);
    }

    private function createIndexableVehicle(): Vehicle
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kawasaki',
            'model' => 'Z650',
            'year' => 2021,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud vastgelegd',
            'km_reading' => 8700,
            'maintenance_date' => '2026-05-18',
            'notes' => 'Factuur en kilometerstand gecontroleerd.',
        ]);

        return $vehicle->refresh();
    }

    private function createNoindexVehicle(): Vehicle
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750X',
            'year' => 2026,
            'is_public' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => '',
            'km_reading' => 0,
            'maintenance_date' => '2026-05-19',
            'notes' => '',
        ]);

        return $vehicle->refresh();
    }

    private function createOutreachVehicle(): Vehicle
    {
        $user = User::factory()->outreachDemo()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'display_variant' => 'Garage demo',
            'year' => 2023,
            'public_slug' => '2023-yamaha-mt-07-garage-demo-31',
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Demo onderhoud met volledige inhoud',
            'km_reading' => 18420,
            'maintenance_date' => '2026-05-20',
            'notes' => 'Demo-data voor outreach.',
        ]);

        return $vehicle->refresh();
    }
}
