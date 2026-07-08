<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class VehicleIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VehicleIntelligenceService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function regularUser(): User
    {
        return User::factory()->create(['is_outreach_demo' => false]);
    }

    private function demoUser(): User
    {
        return User::factory()->outreachDemo()->create();
    }

    private function publicVehicle(User $user, string $brand, string $model, array $extra = []): Vehicle
    {
        return Vehicle::query()->create(array_merge([
            'user_id' => $user->id,
            'brand' => $brand,
            'model' => $model,
            'is_public' => true,
            'public_slug' => "{$brand}-{$model}-{$user->id}",
            'powertrain_type' => 'petrol',
        ], $extra));
    }

    private function addLog(Vehicle $vehicle, string $description, string $date = '2024-01-15'): MaintenanceLog
    {
        return MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => $description,
            'km_reading' => 10000,
            'maintenance_date' => $date,
        ]);
    }

    private function syncAndGet(string $brand, string $model): array
    {
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        return $this->service->forBrandModel($brand, $model);
    }

    // -------------------------------------------------------------------------
    // Specifications – year range
    // -------------------------------------------------------------------------

    public function test_specifications_returns_year_range_for_single_year(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => 2020]);

        $intel = $this->syncAndGet('Yamaha', 'MT-07');

        $this->assertSame('2020', $intel['specifications']['year_range']);
        $this->assertSame(2020, $intel['specifications']['min_year']);
        $this->assertSame(2020, $intel['specifications']['max_year']);
    }

    public function test_specifications_returns_year_range_for_multiple_years(): void
    {
        $u1 = $this->regularUser();
        $u2 = $this->regularUser();
        $this->publicVehicle($u1, 'Honda', 'CB500F', ['year' => 2018, 'public_slug' => 'honda-1']);
        $this->publicVehicle($u2, 'Honda', 'CB500F', ['year' => 2022, 'public_slug' => 'honda-2']);

        $intel = $this->syncAndGet('Honda', 'CB500F');

        $this->assertSame('2018–2022', $intel['specifications']['year_range']);
        $this->assertSame(2018, $intel['specifications']['min_year']);
        $this->assertSame(2022, $intel['specifications']['max_year']);
    }

    public function test_specifications_year_range_is_null_when_no_year_set(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Kawasaki', 'Z900', ['year' => null]);

        $intel = $this->syncAndGet('Kawasaki', 'Z900');

        $this->assertNull($intel['specifications']['year_range']);
        $this->assertNull($intel['specifications']['min_year']);
    }

    // -------------------------------------------------------------------------
    // Specifications – powertrain
    // -------------------------------------------------------------------------

    public function test_specifications_returns_powertrain_labels(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['powertrain_type' => 'petrol']);

        $intel = $this->syncAndGet('Yamaha', 'MT-07');

        $this->assertContains('petrol', $intel['specifications']['powertrains']);
        $this->assertContains('Benzine', $intel['specifications']['powertrain_labels']);
    }

    public function test_specifications_returns_multiple_powertrain_types(): void
    {
        $u1 = $this->regularUser();
        $u2 = $this->regularUser();
        $this->publicVehicle($u1, 'BMW', 'X5', ['powertrain_type' => 'petrol', 'public_slug' => 'bmw-1']);
        $this->publicVehicle($u2, 'BMW', 'X5', ['powertrain_type' => 'diesel', 'public_slug' => 'bmw-2']);

        $intel = $this->syncAndGet('BMW', 'X5');

        $this->assertContains('petrol', $intel['specifications']['powertrains']);
        $this->assertContains('diesel', $intel['specifications']['powertrains']);
        $this->assertContains('Benzine', $intel['specifications']['powertrain_labels']);
        $this->assertContains('Diesel', $intel['specifications']['powertrain_labels']);
    }

    public function test_specifications_excludes_demo_vehicles(): void
    {
        $demo = $this->demoUser();
        $this->publicVehicle($demo, 'Suzuki', 'GSX-R600', ['year' => 2019, 'public_slug' => 'gsxr-demo']);

        $intel = $this->service->forBrandModel('Suzuki', 'GSX-R600');

        $this->assertNull($intel['specifications']['year_range']);
    }

    // -------------------------------------------------------------------------
    // Common maintenance
    // -------------------------------------------------------------------------

    public function test_common_maintenance_returns_descriptions_sorted_by_frequency(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->publicVehicle($user, 'Yamaha', 'MT-07');

        $this->addLog($vehicle, 'Olie vervangen');
        $this->addLog($vehicle, 'Olie vervangen');
        $this->addLog($vehicle, 'Remblokken vervangen');

        $intel = $this->service->forBrandModel('Yamaha', 'MT-07');

        $maintenance = $intel['common_maintenance'];
        $this->assertFalse($maintenance->isEmpty());
        $this->assertSame('Olie vervangen', $maintenance->first()->description);
        $this->assertSame(2, (int) $maintenance->first()->frequency);
    }

    public function test_common_maintenance_returns_empty_collection_when_no_logs(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Honda', 'CB500F');

        $intel = $this->service->forBrandModel('Honda', 'CB500F');

        $this->assertTrue($intel['common_maintenance']->isEmpty());
    }

    public function test_common_maintenance_excludes_demo_vehicle_logs(): void
    {
        $demo = $this->demoUser();
        $demoVehicle = $this->publicVehicle($demo, 'Kawasaki', 'Z900', ['public_slug' => 'z900-demo']);
        $this->addLog($demoVehicle, 'Olie vervangen demo');

        $intel = $this->service->forBrandModel('Kawasaki', 'Z900');

        $descriptions = $intel['common_maintenance']->pluck('description')->all();
        $this->assertNotContains('Olie vervangen demo', $descriptions);
    }

    public function test_common_maintenance_limited_to_10_items(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->publicVehicle($user, 'Yamaha', 'MT-07');

        for ($i = 1; $i <= 15; $i++) {
            $this->addLog($vehicle, "Werkzaamheid {$i}");
        }

        $intel = $this->service->forBrandModel('Yamaha', 'MT-07');

        $this->assertLessThanOrEqual(10, $intel['common_maintenance']->count());
    }

    // -------------------------------------------------------------------------
    // GarageBook stats
    // -------------------------------------------------------------------------

    public function test_garage_book_stats_returns_correct_log_count(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->publicVehicle($user, 'Yamaha', 'MT-07');
        $this->addLog($vehicle, 'Olie vervangen');
        $this->addLog($vehicle, 'Remblokken');

        $intel = $this->service->forBrandModel('Yamaha', 'MT-07');

        $this->assertSame(2, $intel['garage_book_stats']['total_logs']);
    }

    public function test_garage_book_stats_returns_zero_when_no_logs(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Honda', 'CB500F');

        $intel = $this->service->forBrandModel('Honda', 'CB500F');

        $this->assertSame(0, $intel['garage_book_stats']['total_logs']);
        $this->assertSame(0.0, $intel['garage_book_stats']['avg_logs_per_vehicle']);
    }

    public function test_garage_book_stats_computes_average_per_vehicle(): void
    {
        $u1 = $this->regularUser();
        $u2 = $this->regularUser();
        $v1 = $this->publicVehicle($u1, 'Yamaha', 'MT-07', ['public_slug' => 'mt07-a']);
        $v2 = $this->publicVehicle($u2, 'Yamaha', 'MT-07', ['public_slug' => 'mt07-b']);

        $this->addLog($v1, 'Olie');
        $this->addLog($v1, 'Remmen');
        $this->addLog($v2, 'Olie');
        // 3 logs / 2 vehicles = 1.5

        $intel = $this->service->forBrandModel('Yamaha', 'MT-07');

        $this->assertSame(1.5, $intel['garage_book_stats']['avg_logs_per_vehicle']);
    }

    public function test_garage_book_stats_excludes_demo_logs(): void
    {
        $demo = $this->demoUser();
        $demoVehicle = $this->publicVehicle($demo, 'Kawasaki', 'Z900', ['public_slug' => 'z900-demo']);
        $this->addLog($demoVehicle, 'Demo onderhoud');

        $intel = $this->service->forBrandModel('Kawasaki', 'Z900');

        $this->assertSame(0, $intel['garage_book_stats']['total_logs']);
    }

    // -------------------------------------------------------------------------
    // Empty dataset handling (no sections should error)
    // -------------------------------------------------------------------------

    public function test_service_returns_valid_structure_for_unknown_brand_model(): void
    {
        $intel = $this->service->forBrandModel('NonExistent', 'Model X');

        $this->assertIsArray($intel['specifications']);
        $this->assertArrayHasKey('year_range', $intel['specifications']);
        $this->assertArrayHasKey('powertrains', $intel['specifications']);
        $this->assertArrayHasKey('powertrain_labels', $intel['specifications']);

        $this->assertInstanceOf(Collection::class, $intel['common_maintenance']);
        $this->assertTrue($intel['common_maintenance']->isEmpty());

        $this->assertIsArray($intel['garage_book_stats']);
        $this->assertSame(0, $intel['garage_book_stats']['total_logs']);
        $this->assertSame(0.0, $intel['garage_book_stats']['avg_logs_per_vehicle']);

        $this->assertIsArray($intel['known_issues']);
        $this->assertEmpty($intel['known_issues']);
    }

    // -------------------------------------------------------------------------
    // Page integration – new sections visible / conditionally hidden
    // -------------------------------------------------------------------------

    public function test_specs_section_shows_year_when_available(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => 2021]);
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('2021');
    }

    public function test_specs_section_shows_powertrain_label(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['powertrain_type' => 'petrol']);
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('Benzine');
    }

    public function test_specs_section_shows_with_default_powertrain_type(): void
    {
        // powertrain_type defaults to 'petrol' in the Vehicle model, so specs always show
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => null]);
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringContainsString('Benzine', $response->getContent());
    }

    public function test_maintenance_section_shows_when_logs_exist(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->publicVehicle($user, 'Yamaha', 'MT-07');
        $this->addLog($vehicle, 'Kettingreiniging');
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $this->get('/onderhoud/yamaha-mt-07')
            ->assertOk()
            ->assertSee('Kettingreiniging');
    }

    public function test_maintenance_section_hidden_when_no_logs(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07');
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringNotContainsString('Veel uitgevoerde werkzaamheden', $response->getContent());
    }

    public function test_garage_book_stats_section_shows_vehicle_count(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07');
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringContainsString('GarageBook weet', $response->getContent());
    }

    public function test_garage_book_stats_shows_log_count_when_logs_exist(): void
    {
        $user = $this->regularUser();
        $vehicle = $this->publicVehicle($user, 'Yamaha', 'MT-07');
        $this->addLog($vehicle, 'Olie');
        $this->addLog($vehicle, 'Banden');
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringContainsString('Onderhoudslogs geregistreerd', $response->getContent());
    }

    public function test_known_issues_section_hidden_when_no_data(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07');
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringNotContainsString('Bekende aandachtspunten', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Structured data – additionalProperty
    // -------------------------------------------------------------------------

    public function test_structured_data_includes_year_property_when_available(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => 2020]);
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringContainsString('"additionalProperty"', $response->getContent());
        $this->assertStringContainsString('"Bouwjaar"', $response->getContent());
    }

    public function test_structured_data_includes_powertrain_property_when_available(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['powertrain_type' => 'electric']);
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringContainsString('"Brandstoftype"', $response->getContent());
        $this->assertStringContainsString('Elektrisch', $response->getContent());
    }

    public function test_structured_data_always_has_additional_property_for_powertrain(): void
    {
        // powertrain_type is always 'petrol' by default, so additionalProperty is always present
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => null]);
        $this->artisan('garagebook:vehicle-authority:sync')->assertSuccessful();

        $response = $this->get('/onderhoud/yamaha-mt-07')->assertOk();
        $this->assertStringContainsString('"additionalProperty"', $response->getContent());
        $this->assertStringContainsString('"Brandstoftype"', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Caching
    // -------------------------------------------------------------------------

    public function test_intelligence_is_cached_and_returned_from_cache(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => 2021]);

        // First call – warms cache
        $first = $this->service->forBrandModel('Yamaha', 'MT-07');

        // Modify DB after caching
        Vehicle::query()->update(['year' => 2099]);

        // Second call – should return cached (not 2099)
        $second = $this->service->forBrandModel('Yamaha', 'MT-07');

        $this->assertSame($first['specifications']['year_range'], $second['specifications']['year_range']);
        $this->assertNotSame('2099', $second['specifications']['year_range']);
    }

    public function test_flush_for_brand_model_clears_cache(): void
    {
        $user = $this->regularUser();
        $this->publicVehicle($user, 'Yamaha', 'MT-07', ['year' => 2021]);

        $this->service->forBrandModel('Yamaha', 'MT-07');

        Vehicle::query()->update(['year' => 2022]);

        $this->service->flushForBrandModel('Yamaha', 'MT-07');

        $fresh = $this->service->forBrandModel('Yamaha', 'MT-07');
        $this->assertSame('2022', $fresh['specifications']['year_range']);
    }
}
