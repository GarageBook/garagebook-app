<?php

namespace Tests\Feature;

use App\Filament\Widgets\MaintenanceCosts;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceCostsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widget_only_shows_the_authenticated_users_vehicle_costs_and_totals(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Sporttourer',
            'current_km' => 18250,
            'purchase_price' => 8500,
            'insurance_cost_per_month' => 45,
            'road_tax_cost_per_month' => 12,
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'CB650R',
            'current_km' => 9400,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'description' => 'Kleine beurt',
            'km_reading' => 18000,
            'maintenance_date' => now()->subMonth()->toDateString(),
            'cost' => '120.50',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'description' => 'Nieuwe kettingset',
            'km_reading' => 18200,
            'maintenance_date' => now()->subWeeks(2)->toDateString(),
            'cost' => '279.49',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'fuel_date' => now()->subMonth()->toDateString(),
            'distance_km' => 250,
            'fuel_liters' => 14,
            'price_per_liter' => 2.10,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'fuel_date' => now()->subWeeks(2)->toDateString(),
            'distance_km' => 220,
            'fuel_liters' => 13,
            'price_per_liter' => 2.00,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Remblokken',
            'km_reading' => 9300,
            'maintenance_date' => now()->subWeek()->toDateString(),
            'cost' => '999.99',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'fuel_date' => now()->subWeek()->toDateString(),
            'distance_km' => 300,
            'fuel_liters' => 30,
            'price_per_liter' => 2.00,
        ]);

        $this->actingAs($owner);

        Livewire::test(MaintenanceCosts::class)
            ->assertSeeText('Kosten')
            ->assertSeeText('EUR 8.955,39')
            ->assertSeeText('EUR 112,40')
            ->assertSeeText('Totale kosten')
            ->assertSeeText('Maandelijkse kosten')
            ->assertDontSeeText('Sporttourer')
            ->assertDontSeeText('EUR 999,99');
    }

    public function test_dashboard_widget_shows_empty_state_when_user_has_no_vehicles(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MaintenanceCosts::class)
            ->assertSeeText('Kosten')
            ->assertSeeText('Nog geen kosten geregistreerd')
            ->assertDontSeeText('Totale kosten');
    }
}
