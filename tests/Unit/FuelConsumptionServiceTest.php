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

    public function test_it_calculates_consumption_and_conversions_for_miles_layout(): void
    {
        $service = app(FuelConsumptionService::class);

        $distanceKm = 600.3;
        $fuelLiters = 52.0;
        $expectedMiles = $distanceKm / FuelConsumptionService::KILOMETERS_PER_MILE;
        $expectedGallons = $fuelLiters / FuelConsumptionService::LITERS_PER_US_GALLON;
        $expectedMpg = $expectedMiles / $expectedGallons;

        $this->assertEqualsWithDelta(8.662335498917207, $service->calculateLitersPer100Km($distanceKm, $fuelLiters), 0.0000001);
        $this->assertSame(12, $service->calculateRoundedKilometersPerLiterRatio($distanceKm, $fuelLiters));
        $this->assertEqualsWithDelta($expectedMiles, $service->convertKilometersToMiles($distanceKm), 0.0000001);
        $this->assertEqualsWithDelta($distanceKm, $service->convertMilesToKilometers($expectedMiles), 0.0000001);
        $this->assertEqualsWithDelta($expectedGallons, $service->convertLitersToUsGallons($fuelLiters), 0.0000001);
        $this->assertEqualsWithDelta($expectedMpg, $service->calculateMilesPerUsGallon($distanceKm, $fuelLiters), 0.0000001);
        $this->assertSame('27,2', number_format((float) $service->calculateMilesPerUsGallon($distanceKm, $fuelLiters), 1, ',', '.'));
        $this->assertSame('8,66', number_format((float) $service->calculateLitersPer100Km($distanceKm, $fuelLiters), 2, ',', '.'));
    }

    public function test_it_builds_vehicle_specific_consumption_trend(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-05-01',
            'distance_km' => 200,
            'fuel_liters' => 10,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-05-12',
            'distance_km' => 600.3,
            'fuel_liters' => 52,
        ]);

        $trend = app(FuelConsumptionService::class)->getConsumptionTrendForVehicle($user->id, $vehicle->id);

        $this->assertTrue($trend['has_enough_points']);
        $this->assertSame(['01 mei', '12 mei'], $trend['labels']);
        $this->assertSame([5.0, 8.66], $trend['liters_per_100_km']);
        $this->assertSame([47.0, 27.2], $trend['mpg_us']);
    }

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

    public function test_it_calculates_electric_consumption_from_valid_odometer_intervals(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Hyundai',
            'model' => 'Kona Electric',
            'powertrain_type' => Vehicle::POWERTRAIN_ELECTRIC,
        ]);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'entry_type' => FuelLog::ENTRY_TYPE_CHARGE,
            'fuel_date' => '2026-01-01',
            'odometer_km' => 10000,
            'energy_kwh' => 30,
            'total_cost' => 12,
        ]);
        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'entry_type' => FuelLog::ENTRY_TYPE_CHARGE,
            'fuel_date' => '2026-01-10',
            'odometer_km' => 10200,
            'energy_kwh' => 40,
            'total_cost' => 16,
        ]);
        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'entry_type' => FuelLog::ENTRY_TYPE_CHARGE,
            'fuel_date' => '2026-01-12',
            'odometer_km' => 10190,
            'energy_kwh' => 50,
            'total_cost' => 20,
        ]);
        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'entry_type' => FuelLog::ENTRY_TYPE_CHARGE,
            'fuel_date' => '2026-07-01',
            'odometer_km' => 10400,
            'energy_kwh' => 31.5,
            'total_cost' => 10.5,
        ]);

        $stats = app(FuelConsumptionService::class)->getElectricStatsForVehicle($vehicle->fresh(['fuelLogs']));

        $this->assertEqualsWithDelta(17.875, $stats['average_kwh_per_100_km'], 0.000001);
        $this->assertEqualsWithDelta(0.06625, $stats['cost_per_km'], 0.000001);
        $this->assertEqualsWithDelta(20.0, $stats['seasonal']['winter_kwh_per_100_km'], 0.000001);
        $this->assertEqualsWithDelta(15.75, $stats['seasonal']['summer_kwh_per_100_km'], 0.000001);
    }
}
