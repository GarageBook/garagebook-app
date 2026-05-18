<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGaragePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_garage_route_works_without_username_and_renders_seo_markup(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander Hybrid Limited',
            'year' => 2008,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Grote onderhoudsbeurt',
            'km_reading' => 184200,
            'maintenance_date' => '2026-05-01',
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('Onderhoudshistorie van deze 2008 Toyota Highlander Hybrid Limited');
        $response->assertSee('<link rel="canonical" href="' . url('/garage/' . $vehicle->public_slug) . '">', false);
        $response->assertSee('<meta name="robots" content="index,follow">', false);
        $response->assertSee('"@type": "Vehicle"', false);
        $response->assertSee('"@type": "BreadcrumbList"', false);
        $response->assertSee('"@type": "WebPage"', false);
        $response->assertSee('Deze publieke GarageBook-pagina toont de onderhoudshistorie van deze 2008 Toyota Highlander Hybrid Limited.');
    }

    public function test_old_share_route_redirects_permanently_to_new_public_garage_url(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem van Veelen',
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander Hybrid Limited',
            'year' => 2008,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Inspectie uitgevoerd',
            'km_reading' => 182000,
            'maintenance_date' => '2026-05-02',
        ]);

        $this->get('/share/willem-van-veelen/toyota-highlander-hybrid-limited')
            ->assertRedirect(url('/garage/' . $vehicle->public_slug))
            ->assertStatus(301);
    }

    public function test_private_vehicle_returns_not_found_on_public_garage_route(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750S',
            'year' => 2014,
            'is_public' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 25000,
            'maintenance_date' => '2026-05-03',
        ]);

        $this->get('/garage/' . $vehicle->public_slug)
            ->assertNotFound();
    }

    public function test_public_page_hides_owner_identity_email_and_license_plate(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem van Veelen',
            'email' => 'willem@example.com',
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'year' => 1999,
            'license_plate' => '12-AB-34',
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Kleppen gecontroleerd',
            'km_reading' => 42750,
            'maintenance_date' => '2026-05-04',
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertDontSee('Willem van Veelen');
        $response->assertDontSee('willem@example.com');
        $response->assertDontSee('willem-van-veelen');
        $response->assertDontSee('12-AB-34');
    }

    public function test_costs_are_hidden_by_default_on_public_page(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750S',
            'year' => 2014,
            'is_public' => true,
            'share_costs_publicly' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Remblokken vervangen',
            'km_reading' => 33300,
            'maintenance_date' => '2026-05-05',
            'cost' => 123.45,
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertDontSee('Kosten:');
        $response->assertDontSee('123,45');
    }

    public function test_attachments_are_hidden_by_default_on_public_page(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'year' => 2011,
            'is_public' => true,
            'share_attachments_publicly' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Voorvork nagekeken',
            'km_reading' => 67890,
            'maintenance_date' => '2026-05-06',
            'media_attachments' => [
                'maintenance-attachments/foto.jpg',
            ],
            'file_attachments' => [
                'maintenance-attachments/factuur.pdf',
            ],
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertDontSee('maintenance-attachments/foto.jpg');
        $response->assertDontSee('maintenance-attachments/factuur.pdf');
    }

    public function test_public_garage_sitemap_contains_only_indexable_public_vehicles(): void
    {
        $user = User::factory()->create();

        $indexableVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander Hybrid Limited',
            'year' => 2008,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $indexableVehicle->id,
            'description' => 'Motorolie vervangen',
            'km_reading' => 180000,
            'maintenance_date' => '2026-05-07',
        ]);

        $privateVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750S',
            'year' => 2014,
            'is_public' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $privateVehicle->id,
            'description' => 'Ketting gesmeerd',
            'km_reading' => 26000,
            'maintenance_date' => '2026-05-08',
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'year' => 1999,
            'is_public' => true,
        ]);

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(url('/garage/' . $indexableVehicle->public_slug), false)
            ->assertDontSee(url('/garage/' . $privateVehicle->public_slug), false)
            ->assertDontSee('/garage/1999-aprilia-rsv-mille', false);
    }

    public function test_existing_public_slug_stays_unchanged_after_vehicle_identity_fields_change(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander',
            'display_variant' => 'Hybrid',
            'year' => 2008,
            'public_slug' => 'custom-public-slug',
            'is_public' => true,
        ]);

        $vehicle->update([
            'brand' => 'Lexus',
            'model' => 'RX',
            'display_variant' => 'Luxury',
            'year' => 2010,
        ]);

        $this->assertSame('custom-public-slug', $vehicle->fresh()->public_slug);
    }

    public function test_trim_is_used_for_slug_generation_only_when_public_slug_is_empty(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Porsche',
            'model' => '911',
            'display_variant' => 'Carrera 4',
            'year' => 2016,
            'is_public' => true,
        ]);

        $this->assertSame('2016-porsche-911-carrera-4', $vehicle->public_slug);
    }

    public function test_public_page_always_shows_digital_maintenance_book_link(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'NC750S',
            'year' => 2014,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Inspectie uitgevoerd',
            'km_reading' => 28000,
            'maintenance_date' => '2026-05-09',
        ]);

        $this->get('/garage/' . $vehicle->public_slug)
            ->assertOk()
            ->assertSee('https://garagebook.nl/digitaal-onderhoudsboekje/', false);
    }

    public function test_type_specific_link_only_appears_when_type_is_reliably_recognized(): void
    {
        $service = app(PublicGarageService::class);

        $motorcycle = new Vehicle();
        $motorcycle->setRawAttributes([
            'vehicle_type' => 'motorfiets',
        ], true);

        $car = new Vehicle();
        $car->setRawAttributes([
            'category' => 'auto',
        ], true);

        $unknown = new Vehicle();
        $unknown->setRawAttributes([
            'vehicle_type' => 'scooterproject',
        ], true);

        $this->assertSame('https://garagebook.nl/motor-onderhoud-app/', $service->typeSpecificLandingUrl($motorcycle));
        $this->assertSame('https://garagebook.nl/auto-onderhoud-app/', $service->typeSpecificLandingUrl($car));
        $this->assertNull($service->typeSpecificLandingUrl($unknown));
    }

    public function test_pdf_export_route_remains_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12345,
            'maintenance_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get('/maintenance/pdf?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
