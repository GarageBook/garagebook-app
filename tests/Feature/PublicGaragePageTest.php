<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PublicGarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $response->assertSee('Aantoonbare voertuiggeschiedenis van deze 2008 Toyota Highlander Hybrid Limited');
        $response->assertSee('<link rel="canonical" href="' . url('/garage/' . $vehicle->public_slug) . '">', false);
        $response->assertSee('<meta name="robots" content="index,follow">', false);
        $response->assertSee('<meta name="description" content="Bekijk de gedeelde onderhoudsgeschiedenis van deze Toyota Highlander Hybrid Limited in GarageBook. 1 onderhoudsmoment(en) en 1 moment(en) met kilometerstand laten zien wat de eigenaar aantoonbaar heeft vastgelegd.">', false);
        $response->assertSee('"@type": "Vehicle"', false);
        $response->assertSee('"@type": "BreadcrumbList"', false);
        $response->assertSee('"@type": "WebPage"', false);
        $response->assertSee('Onderhoudsmomenten');
        $response->assertSee('Historieperiode');
        $response->assertSee('Met datum en kilometerstand');
        $response->assertSee('Eigenaar bepaalt wat openbaar is');
        $response->assertSee('01-05-2026');
        $response->assertSee('Deze publieke GarageBook-pagina laat zien welke onderhoudsmomenten de eigenaar van deze 2008 Toyota Highlander Hybrid Limited heeft opgebouwd. Onderhoud, kilometerstanden, foto\'s en bewijsstukken worden hier deelbaar samengebracht, terwijl de eigenaar controle houdt over wat openbaar is.');
        $response->assertDontSee('"item": "' . url('/garage') . '"', false);
    }

    public function test_public_page_renders_vehicle_photo_in_16_by_9_hero_container(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        UploadedFile::fake()->image('hero-bike.jpg')->storeAs('vehicle-photos', 'hero-bike.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'Tuono 1000R',
            'year' => 2006,
            'is_public' => true,
            'photo' => 'vehicle-photos/hero-bike.jpg',
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('data-public-vehicle-hero="true"', false);
        $response->assertSee('aspect-ratio:16 / 9;', false);
        $response->assertSee('object-fit:contain;', false);
        $response->assertSee('storage/vehicle-photos/hero-bike.jpg', false);
    }

    public function test_public_page_shows_carousel_controls_for_multiple_vehicle_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        UploadedFile::fake()->image('hero-bike-1.jpg')->storeAs('vehicle-photos', 'hero-bike-1.jpg', 'public');
        UploadedFile::fake()->image('hero-bike-2.jpg')->storeAs('vehicle-photos', 'hero-bike-2.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'year' => 2020,
            'is_public' => true,
            'photo' => 'vehicle-photos/hero-bike-1.jpg',
            'photos' => ['vehicle-photos/hero-bike-2.jpg'],
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('data-public-vehicle-hero-prev="true"', false);
        $response->assertSee('data-public-vehicle-hero-next="true"', false);
    }

    public function test_public_vehicle_photos_merges_primary_photo_and_photo_array_without_losing_valid_unique_images(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        UploadedFile::fake()->image('highlander-1.jpg')->storeAs('vehicle-photos', 'highlander-1.jpg', 'public');
        UploadedFile::fake()->image('highlander-2.jpg')->storeAs('vehicle-photos', 'highlander-2.jpg', 'public');
        UploadedFile::fake()->image('highlander-3.jpg')->storeAs('vehicle-photos', 'highlander-3.jpg', 'public');
        UploadedFile::fake()->create('manual.pdf', 32, 'application/pdf')->storeAs('vehicle-photos', 'manual.pdf', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander Hybrid',
            'year' => 2008,
            'is_public' => true,
            'photo' => '/vehicle-photos/highlander-1.jpg',
            'photos' => [
                'vehicle-photos/highlander-1.jpg',
                '',
                'vehicle-photos/manual.pdf',
                '/vehicle-photos/highlander-2.jpg',
                'vehicle-photos/highlander-3.jpg',
            ],
        ]);

        $photos = app(PublicGarageService::class)->publicVehiclePhotos($vehicle);

        $this->assertCount(3, $photos);
        $this->assertSame([
            'vehicle-photos/highlander-1.jpg',
            'vehicle-photos/highlander-2.jpg',
            'vehicle-photos/highlander-3.jpg',
        ], array_column($photos, 'path'));
    }

    public function test_public_page_renders_all_vehicle_hero_photos_in_order_and_exposes_navigation_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        UploadedFile::fake()->image('highlander-1.jpg')->storeAs('vehicle-photos', 'highlander-1.jpg', 'public');
        UploadedFile::fake()->image('highlander-2.jpg')->storeAs('vehicle-photos', 'highlander-2.jpg', 'public');
        UploadedFile::fake()->image('highlander-3.jpg')->storeAs('vehicle-photos', 'highlander-3.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander Hybrid',
            'year' => 2008,
            'is_public' => true,
            'photo' => 'vehicle-photos/highlander-1.jpg',
            'photos' => [
                'vehicle-photos/highlander-1.jpg',
                'vehicle-photos/highlander-2.jpg',
                'vehicle-photos/highlander-3.jpg',
            ],
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Controle uitgevoerd',
            'km_reading' => 184200,
            'maintenance_date' => '2026-05-01',
        ]);

        $photoUrls = [
            asset('storage/vehicle-photos/highlander-1.jpg'),
            asset('storage/vehicle-photos/highlander-2.jpg'),
            asset('storage/vehicle-photos/highlander-3.jpg'),
        ];

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('data-public-vehicle-hero-total="3"', false);
        $response->assertSee('data-public-vehicle-hero-photos=', false);
        $response->assertSeeInOrder($photoUrls, false);
        $response->assertSee('data-public-vehicle-slide="0"', false);
        $response->assertSee('data-public-vehicle-slide-initial="true"', false);
        $response->assertSee('data-public-vehicle-hero-counter="true">1 / 3<', false);
        $response->assertSee('data-public-vehicle-hero-prev="true"', false);
        $response->assertSee('data-public-vehicle-hero-next="true"', false);
    }

    public function test_public_page_shows_existing_fallback_when_no_vehicle_photo_is_available(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V7 Stone',
            'year' => 2022,
            'is_public' => true,
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSeeText("Nog geen publieke voertuigfoto's zichtbaar");
        $response->assertDontSee('data-public-vehicle-hero="true"', false);
    }

    public function test_public_page_hides_carousel_controls_for_single_vehicle_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        UploadedFile::fake()->image('single-hero-bike.jpg')->storeAs('vehicle-photos', 'single-hero-bike.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'year' => 2004,
            'is_public' => true,
            'photo' => 'vehicle-photos/single-hero-bike.jpg',
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertDontSee('aria-label="Vorige voertuigfoto"', false);
        $response->assertDontSee('aria-label="Volgende voertuigfoto"', false);
    }

    public function test_public_page_shows_proof_indicators_when_costs_and_public_images_are_shared(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $vehiclePhoto = UploadedFile::fake()->image('voertuig.jpg');
        $vehiclePhoto->storeAs('vehicles', 'voertuig.jpg', 'public');

        UploadedFile::fake()->image('bewijs.jpg')->storeAs('maintenance-attachments', 'bewijs.jpg', 'public');
        UploadedFile::fake()->create('factuur.pdf', 100, 'application/pdf')->storeAs('maintenance-attachments', 'factuur.pdf', 'public');
        UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4')->storeAs('maintenance-attachments', 'clip.mp4', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'year' => 2018,
            'photo' => 'vehicles/voertuig.jpg',
            'is_public' => true,
            'share_costs_publicly' => true,
            'share_attachments_publicly' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Jaarbeurt uitgevoerd',
            'km_reading' => 48210,
            'maintenance_date' => '2026-05-11',
            'cost' => 245.95,
            'share_attachments_publicly' => true,
            'attachments' => [
                'maintenance-attachments/bewijs.jpg',
                'maintenance-attachments/factuur.pdf',
                'maintenance-attachments/clip.mp4',
            ],
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('Historieperiode');
        $response->assertSee('Sinds 11-05-2026');
        $response->assertSee('4 zichtbaar');
        $response->assertSee('1 gedeeld');
        $response->assertSee('3 zichtbare bijlagen laten zien wat er aan dit voertuig is gedaan');
        $response->assertSee('Bijlagen en bewijs');
        $response->assertSee('Deel deze onderhoudsgeschiedenis met een koper, garage of liefhebber wanneer je wilt.');
        $response->assertSee('Bij verkoop kan deze historie straks worden overgedragen aan de volgende eigenaar. Die overdracht is nog niet actief.');
        $response->assertSee('Kosten: € 245,95');
        $response->assertSee('storage/maintenance-attachments/bewijs.jpg', false);
        $response->assertSee('storage/maintenance-attachments/factuur.pdf', false);
        $response->assertSee('storage/maintenance-attachments/clip.mp4', false);
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
        $response->assertDontSee('user_id');
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
        $response->assertSee('Kosten inzicht');
        $response->assertSee('Privé gehouden');
        $response->assertDontSee('Kosten:');
        $response->assertDontSee('123,45');
    }

    public function test_maintenance_photos_are_visible_by_default_on_public_page(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        UploadedFile::fake()->image('foto.jpg')->storeAs('maintenance-attachments', 'foto.jpg', 'public');
        UploadedFile::fake()->create('factuur.pdf', 100, 'application/pdf')->storeAs('maintenance-attachments', 'factuur.pdf', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'year' => 2011,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Voorvork nagekeken',
            'km_reading' => 67890,
            'maintenance_date' => '2026-05-06',
            'attachments' => [
                'maintenance-attachments/foto.jpg',
                'maintenance-attachments/factuur.pdf',
            ],
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('Eigenaar bepaalt wat openbaar is');
        $response->assertSee('storage/maintenance-attachments/foto.jpg', false);
        $response->assertDontSee('storage/maintenance-attachments/factuur.pdf', false);
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

    public function test_non_indexable_public_vehicle_uses_noindex_follow_and_stays_out_of_sitemap(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'XT 600',
            'year' => 1994,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => '',
            'km_reading' => 0,
            'maintenance_date' => '2026-05-10',
            'notes' => '',
        ]);

        $this->get('/garage/' . $vehicle->public_slug)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->get('/sitemap-garages.xml')
            ->assertOk()
            ->assertDontSee(url('/garage/' . $vehicle->public_slug), false);
    }

    public function test_public_page_shows_empty_states_when_no_public_history_or_photos_are_available(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V7 Stone',
            'year' => 2022,
            'is_public' => true,
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSeeText('Nog geen publieke voertuigfoto\'s zichtbaar');
        $response->assertSee('Nog geen publiek onderhoud gedeeld');
        $response->assertSee('Deze pagina is publiek zichtbaar, maar nog niet bedoeld voor indexatie zolang de gedeelde historie beperkt is.');
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

    public function test_public_page_renders_shared_maintenance_image_when_enabled(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        UploadedFile::fake()->image('visible-1.jpg')->storeAs('maintenance-attachments', 'visible-1.jpg', 'public');
        UploadedFile::fake()->image('visible-2.jpg')->storeAs('maintenance-attachments', 'visible-2.jpg', 'public');
        UploadedFile::fake()->create('hidden.pdf', 100, 'application/pdf')->storeAs('maintenance-attachments', 'hidden.pdf', 'public');
        UploadedFile::fake()->image('hidden.jpg')->storeAs('maintenance-attachments', 'hidden.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'year' => 2020,
            'is_public' => true,
            'share_attachments_publicly' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Kleine beurt',
            'km_reading' => 12345,
            'maintenance_date' => '2026-05-10',
            'share_attachments_publicly' => true,
            'attachments' => [
                'maintenance-attachments/visible-1.jpg',
                'maintenance-attachments/visible-2.jpg',
                'maintenance-attachments/hidden.pdf',
            ],
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Suzuki',
            'model' => 'SV650',
            'year' => 2019,
            'is_public' => true,
            'share_attachments_publicly' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Andere log',
            'km_reading' => 34567,
            'maintenance_date' => '2026-05-09',
            'share_attachments_publicly' => true,
            'attachments' => ['maintenance-attachments/hidden.jpg'],
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertSee('storage/maintenance-attachments/visible-1.jpg', false);
        $response->assertSee('storage/maintenance-attachments/visible-2.jpg', false);
        $response->assertSee('storage/maintenance-attachments/hidden.pdf', false);
        $response->assertDontSee('storage/maintenance-attachments/hidden.jpg', false);
        $response->assertDontSee('storage/maintenance-attachments/visible.jpg', false);
    }

    public function test_public_page_hides_maintenance_photo_when_photo_visibility_is_disabled_via_array_path(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        UploadedFile::fake()->image('private.jpg')->storeAs('maintenance-attachments', 'private.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Honda',
            'model' => 'CB650R',
            'year' => 2021,
            'is_public' => true,
            'share_attachments_publicly' => false,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Filter vervangen',
            'km_reading' => 23456,
            'maintenance_date' => '2026-05-12',
            'hide_photos_on_public_page' => true,
            'attachments' => [
                ['path' => 'maintenance-attachments/private.jpg'],
            ],
        ]);

        $response = $this->get('/garage/' . $vehicle->public_slug);

        $response->assertOk();
        $response->assertDontSee('storage/maintenance-attachments/private.jpg', false);
    }


    public function test_public_page_hides_maintenance_photo_when_photo_visibility_is_disabled(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        UploadedFile::fake()->image('hidden-photo.jpg')->storeAs('maintenance-attachments', 'hidden-photo.jpg', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Triumph',
            'model' => 'Street Triple',
            'year' => 2023,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Controle uitgevoerd',
            'km_reading' => 3456,
            'maintenance_date' => '2026-05-13',
            'hide_photos_on_public_page' => true,
            'attachments' => [
                'maintenance-attachments/hidden-photo.jpg',
            ],
        ]);

        $this->get('/garage/' . $vehicle->public_slug)
            ->assertOk()
            ->assertDontSee('storage/maintenance-attachments/hidden-photo.jpg', false);
    }

    public function test_non_image_attachments_remain_private_by_default_even_when_photo_is_visible(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        UploadedFile::fake()->image('visible-photo.jpg')->storeAs('maintenance-attachments', 'visible-photo.jpg', 'public');
        UploadedFile::fake()->create('private-invoice.pdf', 100, 'application/pdf')->storeAs('maintenance-attachments', 'private-invoice.pdf', 'public');
        UploadedFile::fake()->create('walkaround.mp4', 100, 'video/mp4')->storeAs('maintenance-attachments', 'walkaround.mp4', 'public');

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Kawasaki',
            'model' => 'Z900',
            'year' => 2022,
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud afgerond',
            'km_reading' => 9876,
            'maintenance_date' => '2026-05-14',
            'attachments' => [
                'maintenance-attachments/visible-photo.jpg',
                'maintenance-attachments/private-invoice.pdf',
                'maintenance-attachments/walkaround.mp4',
            ],
        ]);

        $this->get('/garage/' . $vehicle->public_slug)
            ->assertOk()
            ->assertSee('storage/maintenance-attachments/visible-photo.jpg', false)
            ->assertDontSee('storage/maintenance-attachments/private-invoice.pdf', false)
            ->assertDontSee('storage/maintenance-attachments/walkaround.mp4', false);
    }

}
