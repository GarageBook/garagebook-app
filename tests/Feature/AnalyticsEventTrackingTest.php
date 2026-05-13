<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelLogs\Pages\CreateFuelLog;
use App\Filament\Resources\MaintenanceLogs\Pages\CreateMaintenanceLog;
use App\Filament\Resources\TripLogs\Pages\CreateTripLog;
use App\Filament\Resources\VehicleDocuments\Pages\CreateVehicleDocument;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Jobs\ProcessTripLogUpload;
use App\Models\User;
use App\Models\Vehicle;
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
                    'user_id' => $user->id,
                    'app_section' => 'auth',
                    'method' => 'email',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
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
                    'user_id' => $user->id,
                    'vehicle_id' => Vehicle::query()->firstOrFail()->id,
                    'app_section' => 'vehicles',
                    'source' => 'app',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
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
                    'user_id' => $user->id,
                    'vehicle_id' => $vehicle->id,
                    'app_section' => 'maintenance',
                    'has_cost' => true,
                    'has_attachment' => false,
                    'source' => 'app',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
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
                'name' => 'fuel_entry_created',
                'params' => [
                    'user_id' => $user->id,
                    'vehicle_id' => $vehicle->id,
                    'app_section' => 'fuel',
                    'source' => 'app',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
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
                    'user_id' => $user->id,
                    'vehicle_id' => $vehicle->id,
                    'app_section' => 'documents',
                    'document_type' => 'warranty',
                    'source' => 'app',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
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
                    'user_id' => $user->id,
                    'vehicle_id' => $vehicle->id,
                    'app_section' => 'trips',
                    'source' => 'app',
                ],
            ],
        ], session(AnalyticsEventTracker::SESSION_KEY));
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
                    'user_id' => 42,
                    'vehicle_id' => 99,
                    'app_section' => 'vehicles',
                ],
            ],
        ]);

        $googleTag = view('partials.google-tag')->render();
        $trackingTag = view('partials.analytics-tracking')->render();

        $this->assertStringContainsString('G-TEST123456', $googleTag);
        $this->assertStringContainsString('"garagebook.nl","app.garagebook.nl"', $googleTag);
        $this->assertStringContainsString('page_path: window.location.pathname', $trackingTag);
        $this->assertStringContainsString('hostname: window.location.hostname', $trackingTag);
        $this->assertStringContainsString('"user_id":42', $trackingTag);
        $this->assertStringContainsString('"vehicle_id":99', $trackingTag);
        $this->assertStringContainsString('"app_section":"vehicles"', $trackingTag);
    }
}
