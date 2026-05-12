<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Filament\Resources\FuelLogs\Pages\ListFuelLogs;
use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use App\Services\FuelConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FuelLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_distance_unit_defaults_to_km(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'KTM',
            'model' => '890 Adventure',
        ]);

        $this->assertSame(DistanceUnitService::UNIT_KM, $vehicle->fresh()->distance_unit);
    }

    public function test_resource_query_only_returns_fuel_logs_for_the_authenticated_users_vehicles(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom 650',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Kawasaki',
            'model' => 'Z900',
        ]);

        $ownerFuelLog = FuelLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'fuel_date' => now()->subDay()->toDateString(),
            'distance_km' => 210,
            'fuel_liters' => 11,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 190,
            'fuel_liters' => 10,
        ]);

        $this->actingAs($owner);

        $records = FuelLogResource::getEloquentQuery()->pluck('id')->all();

        $this->assertSame([$ownerFuelLog->id], $records);
    }

    public function test_dashboard_hides_consumption_widget_when_user_has_no_fuel_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSeeText('Gemiddeld verbruik per voertuig');
    }

    public function test_fuel_log_index_persists_the_selected_consumption_unit(): void
    {
        $user = User::factory()->create([
            'consumption_unit' => FuelConsumptionService::UNIT_L_PER_100_KM,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V85 TT',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 200,
            'fuel_liters' => 10,
            'price_per_liter' => 2.099,
        ]);

        $this->actingAs($user);

        Livewire::test(ListFuelLogs::class)
            ->call('setConsumptionUnit', FuelConsumptionService::UNIT_KM_PER_LITER)
            ->assertSeeText('20,00 km/l');

        $this->assertSame(FuelConsumptionService::UNIT_KM_PER_LITER, $user->fresh()->consumption_unit);
    }

    public function test_fuel_log_index_shows_distance_in_vehicle_miles_when_vehicle_uses_miles(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F 900 GS',
            'distance_unit' => DistanceUnitService::UNIT_MILES,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->subDays(8)->toDateString(),
            'odometer_km' => 281757.5,
            'distance_km' => 520.0,
            'fuel_liters' => 45,
            'price_per_liter' => 2.35,
            'station_location' => 'Argos, Waddinxveen',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'odometer_km' => 282357.8,
            'distance_km' => 600.3,
            'fuel_liters' => 52,
            'price_per_liter' => 2.35,
            'station_location' => 'Argos, Bleiswijk',
        ]);

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl(['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSeeText('175.449')
            ->assertSeeText('175.076')
            ->assertSeeText('373 mi')
            ->assertSeeText('600 km')
            ->assertSeeText('52,00 L')
            ->assertSeeText('13,74 gal (US)')
            ->assertSeeText('27,2')
            ->assertSeeText('MPG')
            ->assertSeeText('8,66 L/100 km')
            ->assertSeeText('1:12')
            ->assertSeeText('EUR 2,350')
            ->assertSeeText('Argos, Bleiswijk')
            ->assertSeeText('Verbruik over tijd')
            ->assertSeeText('Bij voertuigen in miles rekenen we automatisch om naar kilometers voor verbruiksberekeningen');
    }

    public function test_fuel_log_index_shows_clear_chart_empty_state_when_there_are_too_few_consumption_points(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tenere 700',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 0,
            'fuel_liters' => 20,
        ]);

        $this->actingAs($user)
            ->get(ListFuelLogs::getUrl(['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSeeText('Verbruik over tijd')
            ->assertSeeText('Nog niet genoeg tankbeurten met afstand om een verbruiksgrafiek te tonen.');
    }
}
