<?php

namespace Tests\Feature;

use App\Filament\Widgets\FuelConsumptionOverview;
use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FuelConsumptionOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widget_only_shows_authenticated_users_vehicle_consumption(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Sporttourer',
            'current_km' => 18250,
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'CB650R',
            'current_km' => 9400,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'fuel_date' => now()->subWeek()->toDateString(),
            'distance_km' => 250,
            'fuel_liters' => 15,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'fuel_date' => now()->subDays(2)->toDateString(),
            'distance_km' => 300,
            'fuel_liters' => 30,
        ]);

        $this->actingAs($owner);

        Livewire::test(FuelConsumptionOverview::class)
            ->assertSeeText('Verbruik')
            ->assertSeeText('Sporttourer')
            ->assertSeeText('6,00 L/100 km')
            ->assertDontSeeText('Honda CB650R')
            ->assertDontSeeText('10,00 L/100 km');
    }

    public function test_widget_toggle_persists_unit_preference_per_user(): void
    {
        $user = User::factory()->create([
            'consumption_unit' => 'l_per_100km',
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 240,
            'fuel_liters' => 12,
        ]);

        $this->actingAs($user);

        Livewire::test(FuelConsumptionOverview::class)
            ->call('setConsumptionUnit', 'km_per_liter')
            ->assertSeeText('20,00 km/l');

        $this->assertSame('km_per_liter', $user->fresh()->consumption_unit);
    }
}
