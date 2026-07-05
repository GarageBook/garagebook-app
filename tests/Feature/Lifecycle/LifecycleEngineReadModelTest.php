<?php

namespace Tests\Feature\Lifecycle;

use App\Enums\LifecycleMilestone;
use App\Enums\LifecycleState;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Lifecycle\EngagementScoreService;
use App\Services\Lifecycle\LifecycleMilestoneService;
use App\Services\Lifecycle\LifecycleStateService;
use App\Services\Lifecycle\VehicleHealthCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleEngineReadModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_vehicle_is_registered(): void
    {
        $user = User::factory()->create();

        $this->assertSame(LifecycleState::REGISTERED, app(LifecycleStateService::class)->determine($user));
        $this->assertSame(0, app(EngagementScoreService::class)->score($user));
        $this->assertSame([], app(LifecycleMilestoneService::class)->achieved($user));
    }

    public function test_user_with_vehicle_reaches_vehicle_added(): void
    {
        $user = User::factory()->create();
        $this->createVehicle($user);

        $this->assertSame(LifecycleState::VEHICLE_ADDED, app(LifecycleStateService::class)->determine($user));
        $this->assertContains(LifecycleMilestone::FIRST_VEHICLE, app(LifecycleMilestoneService::class)->achieved($user));
        $this->assertSame(20, app(EngagementScoreService::class)->score($user));
    }

    public function test_user_with_vehicle_and_maintenance_reaches_first_maintenance_logged(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $this->createMaintenanceLog($vehicle);

        $this->assertSame(LifecycleState::FIRST_MAINTENANCE_LOGGED, app(LifecycleStateService::class)->determine($user));
        $this->assertContains(LifecycleMilestone::FIRST_MAINTENANCE, app(LifecycleMilestoneService::class)->achieved($user));
        $this->assertSame(55, app(EngagementScoreService::class)->score($user));
    }

    public function test_complete_profile_reaches_vehicle_profile_complete(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user, [
            'photo' => 'vehicle-photos/kia.jpg',
            'is_public' => false,
            'public_slug' => null,
        ]);
        $this->createMaintenanceLog($vehicle);

        $this->assertSame(LifecycleState::VEHICLE_PROFILE_COMPLETE, app(LifecycleStateService::class)->determine($user));
        $this->assertContains(LifecycleMilestone::FIRST_PHOTO, app(LifecycleMilestoneService::class)->achieved($user));
        $this->assertContains(LifecycleMilestone::COMPLETE_PROFILE, app(LifecycleMilestoneService::class)->achieved($user));
    }

    public function test_document_presence_reaches_documents_added(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user, [
            'photo' => 'vehicle-photos/kia.jpg',
            'is_public' => false,
            'public_slug' => null,
        ]);
        $this->createMaintenanceLog($vehicle);
        $this->createDocument($vehicle);

        $this->assertSame(LifecycleState::DOCUMENTS_ADDED, app(LifecycleStateService::class)->determine($user));
        $this->assertContains(LifecycleMilestone::FIRST_DOCUMENT, app(LifecycleMilestoneService::class)->achieved($user));
    }

    public function test_public_garage_reaches_public_garage_enabled(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user, [
            'photo' => 'vehicle-photos/kia.jpg',
            'is_public' => true,
            'public_slug' => '2021-kia-ceed',
        ]);
        $this->createMaintenanceLog($vehicle, ['updated_at' => now()->subDays(120)]);
        $this->createDocument($vehicle, ['updated_at' => now()->subDays(120)]);
        $this->createVehicle($user, [
            'brand' => 'Mazda',
            'model' => 'MX-5',
        ]);

        $this->assertSame(LifecycleState::PUBLIC_GARAGE_ENABLED, app(LifecycleStateService::class)->determine($user));
        $this->assertContains(LifecycleMilestone::PUBLIC_GARAGE, app(LifecycleMilestoneService::class)->achieved($user));
    }

    public function test_healthy_garage_requires_all_vehicles_to_have_maintenance_photo_documents_and_recent_activity(): void
    {
        $user = User::factory()->create();
        $first = $this->createCompleteVehicle($user, [
            'photo' => 'vehicle-photos/kia.jpg',
            'is_public' => true,
            'public_slug' => '2021-kia-ceed',
            'updated_at' => now()->subDays(10),
        ]);
        $second = $this->createCompleteVehicle($user, [
            'brand' => 'Mazda',
            'model' => 'MX-5',
            'photo' => 'vehicle-photos/mazda.jpg',
            'updated_at' => now()->subDays(20),
        ]);

        $this->createMaintenanceLog($first);
        $this->createMaintenanceLog($second);
        $this->createDocument($first);
        $this->createDocument($second);

        $this->assertSame(LifecycleState::HEALTHY_GARAGE, app(LifecycleStateService::class)->determine($user));
    }

    public function test_engagement_score_is_capped_between_zero_and_one_hundred(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user, [
            'photo' => 'vehicle-photos/kia.jpg',
            'is_public' => true,
            'public_slug' => '2021-kia-ceed',
        ]);
        $this->createMaintenanceLog($vehicle, ['cost' => 349.95]);
        $this->createDocument($vehicle);
        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'odometer_km' => 42_500,
            'distance_km' => 520,
            'fuel_liters' => 38,
        ]);

        $score = app(EngagementScoreService::class)->score($user);

        $this->assertSame(100, $score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_milestones_are_recognized_from_existing_records(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user, [
            'photo' => 'vehicle-photos/kia.jpg',
            'is_public' => true,
            'public_slug' => '2021-kia-ceed',
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $this->createMaintenanceLog($vehicle, [
                'description' => 'Onderhoud '.$i,
                'cost' => $i === 1 ? 199.95 : null,
                'maintenance_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $this->createDocument($vehicle);
        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 120,
            'fuel_liters' => 8,
        ]);

        $milestones = app(LifecycleMilestoneService::class)->achieved($user);

        $this->assertContains(LifecycleMilestone::FIRST_VEHICLE, $milestones);
        $this->assertContains(LifecycleMilestone::FIRST_MAINTENANCE, $milestones);
        $this->assertContains(LifecycleMilestone::FIRST_PHOTO, $milestones);
        $this->assertContains(LifecycleMilestone::FIRST_DOCUMENT, $milestones);
        $this->assertContains(LifecycleMilestone::PUBLIC_GARAGE, $milestones);
        $this->assertContains(LifecycleMilestone::FIVE_MAINTENANCE_LOGS, $milestones);
        $this->assertContains(LifecycleMilestone::TEN_MAINTENANCE_LOGS, $milestones);
        $this->assertContains(LifecycleMilestone::FIRST_SERVICE_COST, $milestones);
        $this->assertContains(LifecycleMilestone::FIRST_FUEL_LOG, $milestones);
        $this->assertContains(LifecycleMilestone::COMPLETE_PROFILE, $milestones);
    }

    public function test_vehicle_completeness_uses_existing_profile_history_photo_and_document_fields(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $service = app(VehicleHealthCompletenessService::class);

        $this->assertSame(15, $service->score($vehicle->fresh()));

        $vehicle->update([
            'year' => 2021,
            'current_km' => 42_000,
            'license_plate' => 'K-123-AA',
            'photo' => 'vehicle-photos/kia.jpg',
        ]);
        $this->createMaintenanceLog($vehicle);
        $this->createDocument($vehicle);

        $this->assertSame(100, $service->score($vehicle->fresh()));
    }

    private function createVehicle(User $user, array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kia',
            'model' => 'Ceed SW PHEV',
            ...$attributes,
        ]);
    }

    private function createCompleteVehicle(User $user, array $attributes = []): Vehicle
    {
        return $this->createVehicle($user, [
            'year' => 2021,
            'current_km' => 42_000,
            'license_plate' => 'K-123-AA',
            ...$attributes,
        ]);
    }

    private function createMaintenanceLog(Vehicle $vehicle, array $attributes = []): MaintenanceLog
    {
        return MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud',
            'km_reading' => 42_000,
            'maintenance_date' => now()->toDateString(),
            ...$attributes,
        ]);
    }

    private function createDocument(Vehicle $vehicle, array $attributes = []): VehicleDocument
    {
        return VehicleDocument::query()->create([
            'vehicle_id' => $vehicle->id,
            'title' => 'Factuur',
            'document_type' => 'invoice',
            'file_path' => 'vehicle-documents/factuur.pdf',
            ...$attributes,
        ]);
    }
}
