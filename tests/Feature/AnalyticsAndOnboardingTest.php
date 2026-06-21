<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\MaintenanceLogs\Pages\CreateMaintenanceLog;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsAttribution;
use App\Support\AnalyticsEventTracker;
use App\Support\Growth\GrowthDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_outreach_demo_registration_query_parameters_are_captured_and_persisted(): void
    {
        session()->start();

        $this->get('/register?source=outreach_demo&demo_user_id=123&outreach_prospect_id=456&intended=vehicle_create')
            ->assertOk()
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'source' => 'outreach_demo',
                'demo_user_id' => '123',
                'outreach_prospect_id' => '456',
                'intended' => 'vehicle_create',
                'landing_page' => '/register',
            ]);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Outreach Signup',
                'email' => 'outreach-signup@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'outreach-signup@example.com')->firstOrFail();

        $this->assertSame('outreach_demo', $user->registration_source);
        $this->assertDatabaseHas('user_attributions', [
            'user_id' => $user->id,
            'source' => 'outreach_demo',
            'demo_user_id' => 123,
            'outreach_prospect_id' => 456,
            'intended' => 'vehicle_create',
            'landing_page' => '/register',
        ]);

        $events = session(AnalyticsEventTracker::SESSION_KEY, []);
        $registrationCompleted = collect($events)->firstWhere('name', 'registration_completed');

        $this->assertSame('outreach_demo', $registrationCompleted['params']['registration_source'] ?? null);
        $this->assertSame('outreach_demo', $registrationCompleted['params']['source'] ?? null);
        $this->assertSame(123, $registrationCompleted['params']['demo_user_id'] ?? null);
        $this->assertSame(456, $registrationCompleted['params']['outreach_prospect_id'] ?? null);
        $this->assertSame('vehicle_create', $registrationCompleted['params']['intended'] ?? null);
    }

    public function test_ga4_tracking_is_rendered_in_production_when_configured(): void
    {
        $this->app['env'] = 'production';
        config(['analytics.ga4.measurement_id' => 'G-TEST123']);

        $this->get('/')
            ->assertSee('window.garageBookAnalyticsConsent', false)
            ->assertSee('measurementId: "G-TEST123"', false)
            ->assertSee('googletagmanager.com/gtag/js?id=G-TEST123', false)
            ->assertSee("window.gtag('consent', 'default'", false)
            ->assertSee("analytics_storage: 'denied'", false)
            ->assertSee('send_page_view: false', false);
    }

    public function test_ga4_tracking_does_not_render_in_production_without_measurement_id(): void
    {
        $this->app['env'] = 'production';
        config(['analytics.ga4.measurement_id' => null]);

        $this->get('/')
            ->assertDontSee('window.garageBookAnalyticsConsent', false)
            ->assertDontSee('googletagmanager.com/gtag/js?id=', false);
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
        $data = app(GrowthDashboardData::class)->kpiOverview();

        $this->assertNull(collect($data['cards'])->firstWhere('label', 'Conversieratio 30 dagen')['value']);
    }

    public function test_vehicle_creation_redirects_to_maintenance_onboarding(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'brand' => 'Suzuki',
                'model' => 'GSX-R 750',
                'current_km' => 5000,
                'distance_unit' => 'km',
            ])
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(MaintenanceLogResource::getUrl('create', [
                'vehicle_id' => Vehicle::query()->where('brand', 'Suzuki')->value('id'),
                'onboarding' => 1,
            ]));
    }

    public function test_widget_is_visible_for_user_without_vehicle(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Maak je GarageBook compleet')
            ->assertSeeText('0 van 3 stappen voltooid')
            ->assertSeeText('Voertuig toevoegen')
            ->assertSee('href="'.e(VehicleResource::getUrl('create')).'"', false)
            ->assertSee('data-analytics-event="onboarding_vehicle_cta_clicked"', false);
    }

    public function test_widget_is_visible_for_user_with_vehicle_but_without_maintenance(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'current_km' => 18000,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Mooi, je voertuig staat erin.')
            ->assertSeeText('1 van 3 stappen voltooid')
            ->assertSeeText('Voeg je eerste onderhoud toe')
            ->assertSeeText('Bijvoorbeeld een oliebeurt, bandenwissel, kettingonderhoud of reparatie.')
            ->assertSeeText('Factuur of foto toevoegen')
            ->assertSeeText('Jouw onderhoudsboekje')
            ->assertSeeText('Voeg je eerste onderhoud toe om je onderhoudsboekje te starten.')
            ->assertSee('href="'.e(MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id, 'onboarding' => 1])).'"', false)
            ->assertSee('data-analytics-event="quick_maintenance_log_cta_clicked"', false);
    }

    public function test_widget_is_hidden_when_user_has_vehicle_and_maintenance_even_without_document(): void
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
            ->assertDontSeeText('Maak je GarageBook compleet')
            ->assertDontSeeText('3 van 3 stappen voltooid')
            ->assertDontSeeText('100% ingericht')
            ->assertDontSeeText('Aanbevolen volgende stap')
            ->assertDontSeeText('Factuur of foto toevoegen');
    }

    public function test_widget_shows_regular_dashboard_ctas_when_user_is_activated(): void
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
            'maintenance_date' => '2026-05-04',
            'km_reading' => 21000,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Je GarageBook is actief')
            ->assertSeeText('Onderhoud toevoegen')
            ->assertSeeText('Herinnering toevoegen')
            ->assertSeeText('Voeg een rit toe')
            ->assertSeeText('Voeg een document toe')
            ->assertSeeText('Bekijk je voertuigpagina')
            ->assertSeeText('Bekijk je tijdlijn')
            ->assertSeeText('Deel je openbare garage')
            ->assertSeeText('Beheer je voertuigen')
            ->assertDontSeeText('Actief voertuig')
            ->assertDontSeeText('Jouw onderhoudsboekje')
            ->assertDontSeeText('Download onderhoudsboekje');
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

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Mooi, je voertuig staat erin.')
            ->assertSeeText('Voeg je eerste onderhoud toe')
            ->assertDontSeeText('Desmo service');
    }

    public function test_first_maintenance_log_queues_onboarding_completed_event(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'Tuono',
            'current_km' => 12345,
            'distance_unit' => 'km',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Oliebeurt',
                'km_reading' => 12345,
                'maintenance_date' => '2026-06-01',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $events = app(AnalyticsEventTracker::class)->consume();

        $this->assertSame('maintenance_log_created', $events[0]['name'] ?? null);
        $this->assertSame('onboarding_completed', $events[1]['name'] ?? null);
        $this->assertSame('filament', $events[1]['params']['source'] ?? null);
    }
}
