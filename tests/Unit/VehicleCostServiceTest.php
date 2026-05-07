<?php

namespace Tests\Unit;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleCostService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_cost_breakdown_for_only_the_users_vehicles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Sporttourer',
            'purchase_price' => 8500,
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'CB650R',
            'purchase_price' => 9999,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Service',
            'km_reading' => 10000,
            'maintenance_date' => '2026-04-10',
            'cost' => '120.00',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-04-12',
            'distance_km' => 200,
            'fuel_liters' => 10,
            'price_per_liter' => 2.00,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Ignore',
            'km_reading' => 5000,
            'maintenance_date' => '2026-04-14',
            'cost' => '999.00',
        ]);

        $breakdown = app(VehicleCostService::class)->getCostBreakdownByVehicleForUser($user->id);

        $this->assertSame(['Sporttourer'], $breakdown['labels']);
        $this->assertSame([8500.0], $breakdown['purchase']);
        $this->assertSame([120.0], $breakdown['maintenance']);
        $this->assertSame([20.0], $breakdown['fuel']);
    }

    public function test_it_builds_monthly_maintenance_activity_for_only_the_users_vehicles(): void
    {
        Carbon::setTestNow('2026-05-07');

        try {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();

            $vehicle = Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Yamaha',
                'model' => 'Tracer 9',
            ]);

            $otherVehicle = Vehicle::query()->create([
                'user_id' => $otherUser->id,
                'brand' => 'Honda',
                'model' => 'CB650R',
            ]);

            MaintenanceLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'description' => 'Service',
                'km_reading' => 10000,
                'maintenance_date' => '2026-04-10',
                'cost' => '120.00',
            ]);

            FuelLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'fuel_date' => '2026-04-12',
                'distance_km' => 200,
                'fuel_liters' => 10,
                'price_per_liter' => 2.00,
            ]);

            MaintenanceLog::query()->create([
                'vehicle_id' => $otherVehicle->id,
                'description' => 'Ignore',
                'km_reading' => 5000,
                'maintenance_date' => '2026-04-14',
                'cost' => '999.00',
            ]);

            $activity = app(VehicleCostService::class)->getMaintenanceActivityForUser($user->id, 3);

            $this->assertSame(['mrt. 2026', 'apr. 2026', 'mei 2026'], $activity['labels']);
            $this->assertSame([0, 1, 0], $activity['counts']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_builds_cumulative_cost_trend_for_only_the_users_vehicles(): void
    {
        Carbon::setTestNow('2026-05-07');

        try {
            $user = User::factory()->create();
            $vehicle = Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Yamaha',
                'model' => 'Tracer 9',
                'purchase_price' => 8000,
            ]);

            $vehicle->forceFill([
                'created_at' => '2026-03-01 10:00:00',
                'updated_at' => '2026-03-01 10:00:00',
            ])->saveQuietly();

            MaintenanceLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'description' => 'Service',
                'km_reading' => 10000,
                'maintenance_date' => '2026-04-10',
                'cost' => '120.00',
            ]);

            FuelLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'fuel_date' => '2026-05-02',
                'distance_km' => 200,
                'fuel_liters' => 10,
                'price_per_liter' => 2.00,
            ]);

            $trend = app(VehicleCostService::class)->getCumulativeCostTrendForUser($user->id, 3);

            $this->assertSame(['mrt. 2026', 'apr. 2026', 'mei 2026'], $trend['labels']);
            $this->assertSame([8000.0, 120.0, 20.0], $trend['monthly_totals']);
            $this->assertSame([8000.0, 8120.0, 8140.0], $trend['cumulative_totals']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
