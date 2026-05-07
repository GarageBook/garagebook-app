<?php

namespace Tests\Unit;

use App\Models\FuelLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FuelConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelConsumptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_recent_consumption_trend_for_selected_vehicle_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-05-01',
            'distance_km' => 200,
            'fuel_liters' => 10,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-05-06',
            'distance_km' => 240,
            'fuel_liters' => 12,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'fuel_date' => '2026-05-05',
            'distance_km' => 100,
            'fuel_liters' => 20,
        ]);

        $trend = app(FuelConsumptionService::class)->getRecentConsumptionTrendForUser(
            $user->id,
            FuelConsumptionService::UNIT_KM_PER_LITER,
            $vehicle->id,
            8
        );

        $this->assertSame(['01 mei', '06 mei'], $trend['labels']);
        $this->assertSame([20.0, 20.0], $trend['averages']);
    }
}
