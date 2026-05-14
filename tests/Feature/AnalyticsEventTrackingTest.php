<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\FuelLogs\Pages\CreateFuelLog;
use App\Filament\Resources\MaintenanceLogs\Pages\CreateMaintenanceLog;
use App\Filament\Resources\TripLogs\Pages\CreateTripLog;
use App\Filament\Resources\VehicleDocuments\Pages\CreateVehicleDocument;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Jobs\ProcessTripLogUpload;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Analytics;
use App\Support\AnalyticsAttribution;
use App\Support\AnalyticsEventTracker;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsEventTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_event_queues_ga4_payload_in_session(): void
    {
        $user = User::factory()->create([
            'first_login_at' => null,
            'last_login_at' => null,
        ]);

        session()->start();

        Event::dispatch(new Login('web', $user, false));

        $this->assertSame([
            [
                'name' => 'login',
                'params' => [
                    'app_section' => 'auth',
                    'method' => 'email',
                    'user_id_hash' => Analytics::anonymizeIdentifier('user', $user->id),
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['email', 'name', 'user_id']
        );
    }

    public function test_vehicle_create_queues_ga4_payload_in_session(): void
    {
        $user = User::factory()->create();

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'brand' => 'Honda',
                'model' => 'Africa Twin',
                'distance_unit' => 'km',
                'current_km' => 12000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([
            [
                'name' => 'vehicle_created',
                'params' => [
                    'app_section' => 'vehicles',
                    'is_first_vehicle' => true,
                    'vehicle_count_after_create' => 1,
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['license_plate', 'notes', 'vehicle_id', 'brand', 'model']
        );
    }

    public function test_maintenance_log_create_queues_ga4_payload_in_session(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tenere 700',
            'current_km' => 18000,
        ]);

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Olie vervangen',
                'km_reading' => 18100,
                'maintenance_date' => '2026-05-11',
                'cost' => '89.95',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([
            [
                'name' => 'maintenance_log_created',
                'params' => [
                    'app_section' => 'maintenance',
                    'is_first_maintenance_log' => true,
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                    'has_attachments' => false,
                    'cost_entered' => true,
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['description', 'notes', 'vehicle_id']
        );
    }

    public function test_fuel_log_create_queues_ga4_payload_in_session(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F 900 GS',
            'current_km' => 21000,
        ]);

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateFuelLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'fuel_date' => '2026-05-11',
                'odometer_km' => 21100,
                'distance_km' => 240,
                'fuel_liters' => 12.8,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([
            [
                'name' => 'fuel_log_created',
                'params' => [
                    'app_section' => 'fuel',
                    'unit' => 'km',
                    'calculated_consumption_available' => true,
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['vehicle_id', 'station_location']
        );
    }

    public function test_document_upload_queues_ga4_payload_in_session(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Ducati',
            'model' => 'Multistrada V4',
            'current_km' => 9000,
        ]);

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateVehicleDocument::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'title' => 'Garantiebewijs',
                'document_type' => 'warranty',
                'file_path' => UploadedFile::fake()->create('warranty.pdf', 200, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([
            [
                'name' => 'document_uploaded',
                'params' => [
                    'app_section' => 'documents',
                    'document_type' => 'warranty',
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                    'file_count' => 1,
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['original_filename', 'file_path', 'title', 'vehicle_id']
        );
    }

    public function test_trip_log_create_queues_ga4_payload_in_session(): void
    {
        Storage::fake('local');
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'Tuareg 660',
            'current_km' => 4000,
        ]);

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateTripLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'title' => 'Veluwe rit',
                'description' => 'Testrit',
                'source_file_path' => UploadedFile::fake()->create('trip.gpx', 10, 'application/gpx+xml'),
                'source_format' => 'gpx',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(ProcessTripLogUpload::class);

        $this->assertSame([
            [
                'name' => 'trip_log_created',
                'params' => [
                    'app_section' => 'trips',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
    }

    public function test_dashboard_view_event_queues_privacy_safe_counts(): void
    {
        $user = User::factory()->create([
            'first_login_at' => now()->subDay(),
            'last_login_at' => now(),
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom 800',
            'current_km' => 5000,
        ]);

        $vehicle->maintenanceLogs()->create([
            'description' => 'Ketting smeren',
            'km_reading' => 5050,
            'maintenance_date' => '2026-05-11',
        ]);

        $vehicle->documents()->create([
            'title' => 'Factuur',
            'document_type' => 'invoice',
            'file_path' => 'documents/factuur.pdf',
        ]);

        $vehicle->fuelLogs()->create([
            'fuel_date' => '2026-05-11',
            'odometer_km' => 5100,
            'distance_km' => 200,
            'fuel_liters' => 9.5,
        ]);

        session()->start();
        $this->actingAs($user);

        Livewire::test(Dashboard::class);

        $this->assertSame([
            [
                'name' => 'dashboard_viewed',
                'params' => [
                    'app_section' => 'dashboard',
                    'vehicle_count' => 1,
                    'maintenance_log_count' => 1,
                    'document_count' => 1,
                    'fuel_log_count' => 1,
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['user_id', 'vehicle_id', 'email', 'name']
        );
    }

    public function test_registration_event_includes_first_touch_attribution_without_pii(): void
    {
        session()->start();

        session()->put(AnalyticsAttribution::SESSION_KEY, [
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring_launch',
            'utm_content' => 'hero_button',
            'utm_term' => 'garagebook',
            'landing_page' => '/start',
            'referrer' => 'https://garagebook.nl/blogs/test',
        ]);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'GarageBook Tester',
                'email' => 'new-user@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'new-user@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_attributions', [
            'user_id' => $user->id,
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring_launch',
            'utm_content' => 'hero_button',
            'utm_term' => 'garagebook',
            'landing_page' => '/start',
            'referrer' => 'https://garagebook.nl/blogs/test',
        ]);

        $this->assertSame([
            [
                'name' => 'login',
                'params' => [
                    'app_section' => 'auth',
                    'method' => 'email',
                    'user_id_hash' => Analytics::anonymizeIdentifier('user', $user->id),
                ],
            ],
            [
                'name' => 'account_registered',
                'params' => [
                    'app_section' => 'auth',
                    'method' => 'email',
                    'user_id_hash' => Analytics::anonymizeIdentifier('user', $user->id),
                    'source_url' => '/start',
                    'utm_source' => 'newsletter',
                    'utm_medium' => 'email',
                    'utm_campaign' => 'spring_launch',
                    'utm_content' => 'hero_button',
                    'utm_term' => 'garagebook',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[1]['params'],
            ['email', 'name', 'user_id']
        );
    }

    public function test_ga4_partials_do_not_render_outside_production(): void
    {
        $this->assertSame('', trim(view('partials.google-tag')->render()));
        $this->assertSame('', trim(view('partials.analytics-tracking')->render()));
    }

    public function test_ga4_partials_render_configured_tracking_in_production(): void
    {
        $this->app['env'] = 'production';

        config([
            'analytics.ga4.measurement_id' => 'G-TEST123456',
            'analytics.ga4.linker_domains' => ['garagebook.nl', 'app.garagebook.nl'],
        ]);

        session()->start();
        session()->flash(AnalyticsEventTracker::SESSION_KEY, [
            [
                'name' => 'vehicle_created',
                'params' => [
                    'app_section' => 'vehicles',
                    'vehicle_count_after_create' => 2,
                ],
            ],
        ]);

        $googleTag = view('partials.google-tag')->render();
        $trackingTag = view('partials.analytics-tracking')->render();

        $this->assertStringContainsString('G-TEST123456', $googleTag);
        $this->assertStringContainsString('"garagebook.nl","app.garagebook.nl"', $googleTag);
        $this->assertStringContainsString('page_path: window.location.pathname', $trackingTag);
        $this->assertStringContainsString('hostname: window.location.hostname', $trackingTag);
        $this->assertStringContainsString('"app_section":"vehicles"', $trackingTag);
        $this->assertStringContainsString('"vehicle_count_after_create":2', $trackingTag);
    }

    private function assertPayloadDoesNotContainKeys(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }
}
