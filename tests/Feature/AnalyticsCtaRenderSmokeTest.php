<?php

namespace Tests\Feature;

use App\Filament\Resources\FuelLogs\Pages\ListFuelLogs;
use App\Filament\Resources\VehicleDocuments\Pages\ListVehicleDocuments;
use App\Filament\Widgets\MyVehicles;
use App\Models\User;
use App\Models\Vehicle;
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
