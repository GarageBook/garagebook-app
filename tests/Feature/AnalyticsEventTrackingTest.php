<?php

namespace Tests\Feature;

use App\Filament\Auth\GeratelRegister;
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
use App\Support\AnalyticsAttribution;
use App\Support\AnalyticsEventTracker;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
                    'method' => 'email',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['email', 'name', 'user_id', 'user_id_hash', 'source_url', 'utm_content', 'utm_term']
        );
    }

    public function test_vehicle_create_queues_privacy_safe_ga4_payload_in_session(): void
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
                    'source' => 'filament',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['license_plate', 'notes', 'vehicle_id', 'brand', 'model', 'user_id', 'user_id_hash', 'is_first_vehicle']
        );
    }

    public function test_vehicle_create_validation_errors_do_not_queue_event(): void
    {
        $user = User::factory()->create();

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'distance_unit' => 'km',
                'current_km' => 12000,
            ])
            ->call('create')
            ->assertHasFormErrors(['brand', 'model']);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, []);
        $this->assertNull($this->eventParams($events, 'vehicle_created'));
    }

    public function test_maintenance_log_create_queues_privacy_safe_ga4_payload_in_session(): void
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
                    'source' => 'filament',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['description', 'notes', 'vehicle_id', 'cost_entered', 'has_attachments', 'is_first_maintenance_log', 'app_section']
        );
    }

    public function test_maintenance_log_create_validation_errors_do_not_queue_event(): void
    {
        $user = User::factory()->create();

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->fillForm([
                'distance_unit' => 'km',
                'description' => 'Olie vervangen',
            ])
            ->call('create')
            ->assertHasFormErrors(['vehicle_id', 'km_reading', 'maintenance_date']);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, []);
        $this->assertNull($this->eventParams($events, 'maintenance_log_created'));
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
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['vehicle_id', 'station_location']
        );
    }

    public function test_document_upload_queues_privacy_safe_ga4_payload_in_session(): void
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
                    'source' => 'filament',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['original_filename', 'file_path', 'title', 'vehicle_id', 'document_type', 'file_count', 'user_id']
        );
    }

    public function test_document_upload_validation_errors_do_not_queue_event(): void
    {
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
            ])
            ->call('create')
            ->assertHasFormErrors(['file_path']);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, []);
        $this->assertNull($this->eventParams($events, 'document_uploaded'));
    }

    public function test_trip_log_create_queues_privacy_safe_ga4_payload_in_session(): void
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
                'ridden_at' => '2026-05-18',
                'source_file_path' => UploadedFile::fake()->create('trip.gpx', 10, 'application/gpx+xml'),
                'source_format' => 'gpx',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(ProcessTripLogUpload::class);

        $this->assertSame([
            [
                'name' => 'trip_created',
                'params' => [
                    'source' => 'filament',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));

        $this->assertPayloadDoesNotContainKeys(
            session(AnalyticsEventTracker::SESSION_KEY)[0]['params'],
            ['title', 'description', 'vehicle_id', 'distance_km', 'source_file_path', 'source_format', 'location', 'route']
        );
    }

    public function test_trip_log_create_validation_errors_do_not_queue_event(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $otherUserVehicle = Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => 'Aprilia',
            'model' => 'Tuareg 660',
            'current_km' => 4000,
        ]);

        session()->start();
        $this->actingAs($user);

        Livewire::test(CreateTripLog::class)
            ->fillForm([
                'vehicle_id' => $otherUserVehicle->id,
                'title' => 'Veluwe rit',
                'ridden_at' => '2026-05-18',
                'source_file_path' => UploadedFile::fake()->create('trip.gpx', 10, 'application/gpx+xml'),
                'source_format' => 'gpx',
            ])
            ->call('create')
            ->assertHasErrors(['data.vehicle_id']);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, []);
        $this->assertNull($this->eventParams($events, 'trip_created'));
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

    public function test_register_page_renders_registration_started_with_safe_utm_params(): void
    {
        $this->app['env'] = 'production';

        config([
            'analytics.ga4.measurement_id' => 'G-TEST123456',
        ]);

        $this->get('/admin/register?utm_source=garagebook&utm_medium=referral&utm_campaign=spring_launch&utm_content=hero&utm_term=garagebook')
            ->assertOk()
            ->assertSee('registration_started', false)
            ->assertSee('"utm_source":"garagebook"', false)
            ->assertSee('"utm_medium":"referral"', false)
            ->assertSee('"utm_campaign":"spring_launch"', false)
            ->assertSee('"page_path":"\/admin\/register"', false)
            ->assertSee('"page_location"', false);
    }

    public function test_registration_started_queue_only_keeps_safe_marketing_params(): void
    {
        session()->start();

        $request = Request::create('/admin/register?utm_source=garagebook&utm_medium=referral&utm_campaign=spring_launch&utm_content=hero&utm_term=garagebook', 'GET');
        $this->app->instance('request', $request);

        app(AnalyticsEventTracker::class)->queueRegisterStart([
            'utm_source' => 'garagebook',
            'utm_medium' => 'referral',
            'utm_campaign' => 'spring_launch',
            'utm_content' => 'hero',
            'utm_term' => 'garagebook',
        ]);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, ['registration_started']);
        $this->assertSame('/admin/register', $this->eventParams($events, 'registration_started')['page_path'] ?? null);
        $this->assertSame('garagebook', $this->eventParams($events, 'registration_started')['utm_source'] ?? null);
        $this->assertSame('referral', $this->eventParams($events, 'registration_started')['utm_medium'] ?? null);
        $this->assertSame('spring_launch', $this->eventParams($events, 'registration_started')['utm_campaign'] ?? null);
        $this->assertStringContainsString('/admin/register?', $this->eventParams($events, 'registration_started')['page_location'] ?? '');
        $this->assertStringContainsString('utm_source=garagebook', $this->eventParams($events, 'registration_started')['page_location'] ?? '');
        $this->assertStringContainsString('utm_medium=referral', $this->eventParams($events, 'registration_started')['page_location'] ?? '');
        $this->assertStringContainsString('utm_campaign=spring_launch', $this->eventParams($events, 'registration_started')['page_location'] ?? '');

        $this->assertPayloadDoesNotContainKeys(
            $this->eventParams($events, 'registration_started'),
            ['utm_content', 'utm_term', 'source_url', 'user_id_hash', 'email', 'name']
        );
    }

    public function test_registration_success_queues_registration_completed_after_successful_user_creation(): void
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

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, ['login', 'registration_completed']);
        $this->assertSame(['method' => 'email'], $this->eventParams($events, 'login'));
        $this->assertSame(['method' => 'email'], $this->eventParams($events, 'registration_completed'));

        $this->assertPayloadDoesNotContainKeys(
            $this->eventParams($events, 'registration_completed'),
            ['email', 'name', 'user_id', 'user_id_hash', 'source_url', 'utm_content', 'utm_term', 'utm_source', 'utm_medium', 'utm_campaign', 'registration_source']
        );
    }

    public function test_geratel_registration_success_queues_registration_completed_with_registration_source(): void
    {
        session()->start();

        Livewire::test(GeratelRegister::class)
            ->fillForm([
                'name' => 'Geratel Tester',
                'email' => 'geratel-analytics@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'geratel-analytics@example.com')->firstOrFail();

        $this->assertSame('geratel', $user->registration_source);
        $this->assertTrue($user->isGeratelUser());

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, ['login', 'registration_completed']);
        $this->assertSame(['method' => 'email', 'registration_source' => 'geratel'], $this->eventParams($events, 'registration_completed'));

        $this->assertPayloadDoesNotContainKeys(
            $this->eventParams($events, 'registration_completed'),
            ['email', 'name', 'user_id', 'user_id_hash', 'source_url', 'utm_content', 'utm_term', 'utm_source', 'utm_medium', 'utm_campaign']
        );
    }

    public function test_registration_validation_errors_do_not_queue_registration_completed(): void
    {
        session()->start();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'GarageBook Tester',
                'email' => 'not-an-email',
                'password' => 'password',
                'passwordConfirmation' => 'mismatch',
            ])
            ->call('register')
            ->assertHasFormErrors(['email', 'password']);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);

        $this->assertSessionEventNames($events, []);
        $this->assertNull($this->eventParams($events, 'registration_completed'));
    }

    public function test_consume_pulls_queued_events_once(): void
    {
        session()->start();
        session()->flash(AnalyticsEventTracker::SESSION_KEY, [
            [
                'name' => 'vehicle_created',
                'params' => [
                    'source' => 'filament',
                ],
            ],
        ]);

        $events = app(AnalyticsEventTracker::class)->consume();

        $this->assertSame([
            [
                'name' => 'vehicle_created',
                'params' => [
                    'source' => 'filament',
                ],
            ],
        ], $events);
        $this->assertSame([], session(AnalyticsEventTracker::SESSION_KEY, []));
        $this->assertSame([], app(AnalyticsEventTracker::class)->consume());
    }

    public function test_queued_events_are_consumed_after_render_and_do_not_fire_twice(): void
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
                    'source' => 'filament',
                ],
            ],
        ]);

        $firstRender = view('partials.analytics-tracking')->render();
        $secondRender = view('partials.analytics-tracking')->render();

        $this->assertStringContainsString('vehicle_created', $firstRender);
        $this->assertStringContainsString('page_location: window.location.href', $firstRender);
        $this->assertStringContainsString('page_path: window.location.pathname', $firstRender);
        $this->assertStringNotContainsString('vehicle_created', $secondRender);
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
                    'source' => 'filament',
                ],
            ],
        ]);

        $googleTag = view('partials.google-tag')->render();
        $trackingTag = view('partials.analytics-tracking')->render();

        $this->assertStringContainsString('G-TEST123456', $googleTag);
        $this->assertStringContainsString('"garagebook.nl","app.garagebook.nl"', $googleTag);
        $this->assertStringContainsString('page_location: window.location.href', $trackingTag);
        $this->assertStringContainsString('page_path: window.location.pathname', $trackingTag);
        $this->assertStringContainsString('lastPageViewKey', $trackingTag);
        $this->assertStringContainsString('livewireListenerRegistered', $trackingTag);
        $this->assertStringContainsString("window.garagebookTrack('page_view')", $trackingTag);
        $this->assertStringContainsString('"source":"filament"', $trackingTag);
        $this->assertStringNotContainsString('hostname:', $trackingTag);
    }

    private function assertPayloadDoesNotContainKeys(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    private function assertSessionEventNames(array $events, array $expectedNames): void
    {
        $this->assertSame($expectedNames, array_map(
            fn (array $event): string => $event['name'],
            $events,
        ));
    }

    private function assertSessionEventName(array $events, string $expectedName): void
    {
        $this->assertContains($expectedName, array_map(
            fn (array $event): string => $event['name'],
            $events,
        ));
    }

    private function eventParams(array $events, string $eventName): ?array
    {
        foreach ($events as $event) {
            if (($event['name'] ?? null) === $eventName) {
                return $event['params'] ?? null;
            }
        }

        return null;
    }
}