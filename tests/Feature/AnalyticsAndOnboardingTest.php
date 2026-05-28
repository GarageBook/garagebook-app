<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\AnalyticsAttribution;
use App\Support\AnalyticsEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_utm_parameters_are_captured_and_persist(): void
    {
        $this->get('/?utm_source=partner&utm_medium=email&utm_campaign=summer_sale')
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'utm_source' => 'partner',
                'utm_medium' => 'email',
                'utm_campaign' => 'summer_sale',
                'landing_page' => '/',
            ]);

        // Visit another page without UTMs, should still be there
        $this->get('/blogs')
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY);
    }

    public function test_existing_utm_parameters_remain_first_touch_values(): void
    {
        $this->get('/?utm_source=first&utm_medium=email')
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'utm_source' => 'first',
                'utm_medium' => 'email',
                'landing_page' => '/',
            ]);

        $this->get('/?utm_source=second&utm_campaign=win')
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'utm_source' => 'first',
                'utm_medium' => 'email',
                'landing_page' => '/',
            ]);
    }

    public function test_ga4_tracking_is_rendered_in_production_when_configured(): void
    {
        $this->app['env'] = 'production';
        config(['analytics.ga4.measurement_id' => 'G-TEST123']);

        $this->get('/')
            ->assertSee('window.garageBookAnalyticsConsent', false)
            ->assertSee('measurementId: "G-TEST123"', false)
            ->assertDontSee('googletagmanager.com/gtag/js?id=G-TEST123', false);
    }

    public function test_onboarding_redirect_after_vehicle_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'current_km' => 12000,
        ]);

        app(AnalyticsEventTracker::class)->queueVehicleCreated($vehicle);

        $events = app(AnalyticsEventTracker::class)->consume();
        $this->assertCount(1, $events);
        $this->assertEquals('vehicle_created', $events[0]['name']);
        $this->assertEquals('filament', $events[0]['params']['source']);
    }

    public function test_growth_metrics_sanity_checks(): void
    {
        $data = app(\App\Support\Growth\GrowthDashboardData::class)->kpiOverview();

        // With 0 visitors and 0 registrations, conversion should be null or 0
        $this->assertNull(collect($data['cards'])->firstWhere('label', 'Conversieratio 30 dagen')['value']);
    }

    public function test_vehicle_creation_redirects_to_maintenance_onboarding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Filament\Resources\Vehicles\Pages\CreateVehicle::class)
            ->fillForm([
                'brand' => 'Suzuki',
                'model' => 'GSX-R 750',
                'current_km' => 5000,
                'distance_unit' => 'km',
            ])
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $vehicle = Vehicle::where('brand', 'Suzuki')->first();
        $this->assertNotNull($vehicle);
    }

    public function test_user_without_vehicles_sees_vehicle_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Voeg je eerste voertuig toe')
            ->assertSeeText('Voertuig toevoegen')
            ->assertDontSeeText('Voeg je eerste onderhoud toe');
    }

    public function test_user_with_vehicle_but_without_maintenance_sees_maintenance_nudge(): void
    {
        $user = User::factory()->create();
        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'current_km' => 18000,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Voeg je eerste onderhoud toe')
            ->assertSeeText('Onderhoud toevoegen')
            ->assertSeeText('Voertuig aanpassen')
            ->assertDontSeeText('Document uploaden');
    }

    public function test_user_with_vehicle_and_maintenance_no_longer_sees_large_step_two_onboarding(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'current_km' => 32000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Kleine beurt',
            'maintenance_date' => '2026-05-01',
            'km_reading' => 32000,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Maak je garage completer')
            ->assertSeeText('Document uploaden')
            ->assertDontSeeText('Stap 2: Voeg je eerste onderhoud toe')
            ->assertDontSeeText('Voertuig aanpassen');
    }

    public function test_user_with_vehicle_maintenance_and_document_sees_no_onboarding_widget(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Triumph',
            'model' => 'Street Triple',
            'current_km' => 21000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Grote beurt',
            'maintenance_date' => '2026-03-10',
            'km_reading' => 21000,
        ]);

        VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Onderhoudsfactuur',
            'document_type' => 'invoice',
            'file_path' => 'documents/onderhoudsfactuur.pdf',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertDontSeeText('Voeg je eerste voertuig toe')
            ->assertDontSeeText('Voeg je eerste onderhoud toe')
            ->assertDontSeeText('Maak je garage completer')
            ->assertDontSeeText('Document uploaden');
    }

    public function test_other_users_data_does_not_affect_dashboard_onboarding_state(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Ducati',
            'model' => 'Monster',
            'current_km' => 9000,
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kawasaki',
            'model' => 'Z900',
            'current_km' => 15000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Desmo service',
            'maintenance_date' => '2026-04-20',
            'km_reading' => 9000,
        ]);

        VehicleDocument::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'title' => 'Factuur andere user',
            'document_type' => 'invoice',
            'file_path' => 'documents/other-user.pdf',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Voeg je eerste onderhoud toe')
            ->assertSeeText('Onderhoud toevoegen')
            ->assertSeeText('Voertuig aanpassen')
            ->assertDontSeeText('Maak je garage completer')
            ->assertDontSeeText('Factuur andere user');
    }
}
