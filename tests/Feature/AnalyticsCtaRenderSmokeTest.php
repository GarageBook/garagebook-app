<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelLogs\Pages\ListFuelLogs;
use App\Filament\Resources\VehicleDocuments\Pages\ListVehicleDocuments;
use App\Filament\Widgets\DashboardOnboardingWidget;
use App\Filament\Widgets\MyVehicles;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsCtaRenderSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_empty_state_renders_app_cta_clicked_tracking_attributes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MyVehicles::class)
            ->assertSeeHtml('data-analytics-click="true"')
            ->assertSeeHtml('data-analytics-event="app_cta_clicked"')
            ->assertSeeHtml('data-analytics-param-cta-name="add_first_vehicle"')
            ->assertSeeHtml('data-analytics-param-location="my_vehicles_empty_state"')
            ->assertSeeHtml('data-analytics-param-user-state="new"');
    }

    public function test_dashboard_onboarding_widget_renders_vehicle_tracking_attributes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DashboardOnboardingWidget::class)
            ->assertSeeHtml('data-analytics-click="true"')
            ->assertSeeHtml('data-analytics-event="onboarding_vehicle_cta_clicked"')
            ->assertSeeHtml('data-analytics-param-location="dashboard_onboarding_widget"');
    }

    public function test_dashboard_onboarding_widget_renders_maintenance_tracking_attributes(): void
    {
        $user = User::factory()->create();
        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
            'current_km' => 12000,
        ]);

        $this->actingAs($user);

        Livewire::test(DashboardOnboardingWidget::class)
            ->assertSeeHtml('data-analytics-click="true"')
            ->assertSeeHtml('data-analytics-event="quick_maintenance_log_cta_clicked"')
            ->assertSeeHtml('data-analytics-param-location="dashboard_onboarding_widget"');
    }

    public function test_onboarding_widget_tracking_normalizes_array_next_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(AnalyticsEventTracker::class)->queueOnboardingWidgetViewed([
            'key' => 'maintenance',
        ], 1, 3);

        $events = app(AnalyticsEventTracker::class)->consume();

        $this->assertSame('onboarding_widget_viewed', $events[0]['name'] ?? null);
        $this->assertSame('maintenance', $events[0]['params']['next_step'] ?? null);
        $this->assertSame(1, $events[0]['params']['completed_steps'] ?? null);
        $this->assertSame(3, $events[0]['params']['total_steps'] ?? null);
    }

    public function test_dashboard_onboarding_widget_renders_booklet_download_tracking_after_activation(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
            'current_km' => 12000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12000,
            'maintenance_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-analytics-event="maintenance_booklet_downloaded"', false)
            ->assertSee('data-analytics-param-location="dashboard_actions_booklet"', false);
    }

    public function test_fuel_logs_page_renders_app_cta_clicked_tracking_attributes(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
            'current_km' => 12000,
        ]);

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl(['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSee('data-analytics-event="app_cta_clicked"', false)
            ->assertSee('data-analytics-param-cta-name="add_fuel_log"', false)
            ->assertSee('data-analytics-param-location="fuel_logs_header"', false);
    }

    public function test_vehicle_documents_page_renders_app_cta_clicked_tracking_attributes(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F 850 GS',
            'current_km' => 8000,
        ]);

        $this->actingAs($user)
            ->get(ListVehicleDocuments::getUrl(['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSee('data-analytics-event="app_cta_clicked"', false)
            ->assertSee('data-analytics-param-cta-name="upload_document"', false)
            ->assertSee('data-analytics-param-location="vehicle_documents_header"', false);
    }
}
