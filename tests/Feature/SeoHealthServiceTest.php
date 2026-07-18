<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use App\Services\Seo\SeoHealthService;
use App\Support\PublicSeoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_outreach_vehicle_is_not_indexable_or_sitemap_eligible(): void
    {

        $user = User::factory()->outreachDemo()->create();
        $vehicle = $this->createPublicVehicle($user, '2023-yamaha-mt-07-garage-demo-31');
        $this->createMaintenanceLog($vehicle, 'Uitgebreide demo onderhoudsregel voor outreach inspectie.');

        $report = app(SeoHealthService::class)->report();

        $this->assertFalse(app(PublicGarageService::class)->shouldIndex($vehicle->fresh(['user', 'maintenanceLogs'])));
        $this->assertSame(1, $report['overview']['demo_outreach_vehicles']);
        $this->assertSame(0, $report['sitemap']['eligible_count']);
        $this->assertSame([], $report['sitemap']['demo_outreach_urls']);
    }

    public function test_product_schema_is_detected_when_present_in_garage_html(): void
    {
        $inspection = app(SeoHealthService::class)->inspectGarageHtml(<<<'HTML'
            <html><head><script type="application/ld+json">{"@context":"https://schema.org","@type":"Product","name":"Geen verkoopproduct"}</script></head></html>
            HTML);

        $this->assertTrue($inspection['has_product_schema']);
        $this->assertFalse($inspection['has_vehicle_schema']);
    }

    public function test_sitemap_eligible_count_matches_public_garage_service(): void
    {

        $publicUser = User::factory()->create();
        $indexableVehicle = $this->createPublicVehicle($publicUser, '2021-kawasaki-z650');
        $this->createMaintenanceLog($indexableVehicle, 'Volledige onderhoudsregel met duidelijke publieke omschrijving.');

        $noindexVehicle = $this->createPublicVehicle($publicUser, '2022-kawasaki-versys1000');
        $this->createMaintenanceLog($noindexVehicle, '');

        $demoUser = User::factory()->outreachDemo()->create();
        $demoVehicle = $this->createPublicVehicle($demoUser, '2023-yamaha-mt-07-garage-demo-31');
        $this->createMaintenanceLog($demoVehicle, 'Volledige demo onderhoudsregel met duidelijke omschrijving.');

        $report = app(SeoHealthService::class)->report();
        $serviceCount = app(PublicGarageService::class)->indexableVehicles()->count();

        $this->assertSame($serviceCount, $report['sitemap']['eligible_count']);
        $this->assertSame(2, $report['sitemap']['eligible_count']);
        $this->assertSame([
            app(PublicGarageService::class)->canonicalUrl($indexableVehicle),
            app(PublicGarageService::class)->canonicalUrl($noindexVehicle),
        ], $report['sitemap']['urls']);
    }

    public function test_weak_public_pages_are_reported(): void
    {

        $user = User::factory()->create(['email' => 'owner@example.com']);
        $vehicle = $this->createPublicVehicle($user, '2026-honda-nc750x');
        $this->createMaintenanceLog($vehicle, '');

        $report = app(SeoHealthService::class)->report();

        $this->assertNotEmpty($report['weak_pages']);
        $row = collect($report['weak_pages'])->firstWhere('slug', '2026-honda-nc750x');

        $this->assertNotNull($row);
        $this->assertSame('owner@example.com', $row['owner']);
        $this->assertContains('geen foto', $row['reasons']);
        $this->assertContains('korte/lege logomschrijving', $row['reasons']);
    }

    public function test_dashboard_public_links_use_the_vehicle_public_slug(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $vehicle = $this->createPublicVehicle($user, '1974-honda-c50-super-cub');
        $vehicle->forceFill([
            'brand' => 'Honda',
            'model' => 'C50',
            'year' => 1974,
        ])->save();

        $report = app(SeoHealthService::class)->report();
        $row = collect($report['weak_pages'])->firstWhere('vehicle', '1974 Honda C50');

        $this->assertNotNull($row);
        $this->assertSame('1974-honda-c50-super-cub', $row['slug']);
        $this->assertSame(
            PublicSeoUrl::garage('1974-honda-c50-super-cub'),
            $row['public_url']
        );
        $this->assertFalse(str_ends_with($row['public_url'], '/garage/honda-c50'));
    }

    public function test_public_vehicle_without_maintenance_or_photo_is_indexable(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createPublicVehicle($user, '2026-honda-cb750-hornet');

        $this->assertTrue(app(PublicGarageService::class)->shouldIndex($vehicle->fresh(['user', 'maintenanceLogs'])));
    }

    public function test_hidden_vehicle_is_not_indexable(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createPublicVehicle($user, '2026-hidden-honda');
        $vehicle->forceFill(['is_public' => false])->save();

        $this->assertFalse(app(PublicGarageService::class)->shouldIndex($vehicle->fresh(['user', 'maintenanceLogs'])));
    }

    private function createPublicVehicle(User $user, string $slug): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750X',
            'year' => 2026,
            'public_slug' => $slug,
            'is_public' => true,
        ]);
    }

    private function createMaintenanceLog(Vehicle $vehicle, string $description): MaintenanceLog
    {
        return MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => $description,
            'km_reading' => 12000,
            'maintenance_date' => '2026-05-20',
        ]);
    }
}
