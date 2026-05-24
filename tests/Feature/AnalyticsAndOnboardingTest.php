<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsAttribution;
use App\Support\AnalyticsEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
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

    public function test_utm_parameters_can_be_updated_with_new_values(): void
    {
        $this->get('/?utm_source=first&utm_medium=email')
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'utm_source' => 'first',
                'utm_medium' => 'email',
                'landing_page' => '/',
            ]);

        $this->get('/?utm_source=second&utm_campaign=win')
            ->assertSessionHas(AnalyticsAttribution::SESSION_KEY, [
                'utm_source' => 'second',
                'utm_medium' => 'email',
                'utm_campaign' => 'win',
                'landing_page' => '/',
            ]);
    }

    public function test_ga4_tracking_is_rendered_in_production_when_configured(): void
    {
        $this->app['env'] = 'production';
        config(['analytics.ga4.measurement_id' => 'G-TEST123']);

        $this->get('/')
            ->assertSee('googletagmanager.com/gtag/js?id=G-TEST123')
            ->assertSee('send_page_view: false', false);

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
}
