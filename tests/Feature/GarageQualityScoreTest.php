<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Lifecycle\Conditions\PublicGarageLifecycleConditions;
use App\Services\Seo\GarageQualityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GarageQualityScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_public_garage_scores_one_hundred(): void
    {
        Storage::fake('public');
        UploadedFile::fake()->image('vehicle.jpg')->storeAs('vehicle-photos', 'vehicle.jpg', 'public');
        $vehicle = $this->vehicle(['photo' => 'vehicle-photos/vehicle.jpg', 'year' => 2020, 'display_variant' => 'Touring', 'current_km' => 42_000]);

        foreach (range(1, 3) as $index) {
            MaintenanceLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'description' => 'Uitgebreide onderhoudsbeurt '.$index,
                'km_reading' => 40_000 + $index,
                'maintenance_date' => now()->subDays($index)->toDateString(),
            ]);
        }

        $result = app(GarageQualityScore::class)->score($vehicle->fresh(['user', 'maintenanceLogs']));

        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['reason_codes']);
    }

    public function test_short_but_meaningful_apk_description_is_accepted(): void
    {
        $vehicle = $this->vehicle(['year' => 2020, 'current_km' => 42_000]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'APK',
            'km_reading' => 42_000,
            'maintenance_date' => now()->toDateString(),
        ]);

        $result = app(GarageQualityScore::class)->score($vehicle->fresh(['user', 'maintenanceLogs']));

        $this->assertNotContains('short_log_descriptions', $result['reason_codes']);
    }

    public function test_missing_public_content_returns_atomized_reason_codes(): void
    {
        $vehicle = $this->vehicle(['brand' => '', 'model' => '', 'year' => null]);

        $result = app(GarageQualityScore::class)->score($vehicle->fresh(['user', 'maintenanceLogs']));

        $this->assertSame(0, $result['score']);
        $this->assertContains('missing_vehicle_identity', $result['reason_codes']);
        $this->assertContains('missing_photo', $result['reason_codes']);
        $this->assertContains('no_maintenance_logs', $result['reason_codes']);
    }

    public function test_lifecycle_public_garage_conditions_are_shadow_only_helpers(): void
    {
        $vehicle = $this->vehicle();
        $conditions = app(PublicGarageLifecycleConditions::class);

        $this->assertTrue($conditions->publicVehicleWithoutPhoto($vehicle->fresh(['user', 'maintenanceLogs'])));
        $this->assertTrue($conditions->publicVehicleWithoutMaintenance($vehicle->fresh(['user', 'maintenanceLogs'])));
        $this->assertTrue($conditions->lowGarageQualityScore($vehicle->fresh(['user', 'maintenanceLogs'])));
    }

    private function vehicle(array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => 'Honda',
            'model' => 'CB750',
            'public_slug' => 'honda-cb750',
            'is_public' => true,
            ...$attributes,
        ]);
    }
}
