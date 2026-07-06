<?php

namespace Tests\Feature\Lifecycle;

use App\Enums\LifecycleMilestone;
use App\Enums\LifecycleState;
use App\Events\Lifecycle\LifecycleStateChanged;
use App\Mail\Lifecycle\LifecycleEmailMailable;
use App\Models\FuelLog;
use App\Models\LifecycleMilestoneEntry;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Lifecycle\LifecycleProgressSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleEventsObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_created_triggers_first_vehicle_milestone(): void
    {
        $user = User::factory()->create();

        $this->createVehicle($user);

        $this->assertMilestone($user, LifecycleMilestone::FIRST_VEHICLE);
        $this->assertCurrentState($user, LifecycleState::VEHICLE_ADDED);
    }

    public function test_maintenance_created_triggers_first_maintenance_milestone(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $this->createMaintenanceLog($vehicle);

        $this->assertMilestone($user, LifecycleMilestone::FIRST_MAINTENANCE);
        $this->assertCurrentState($user, LifecycleState::FIRST_MAINTENANCE_LOGGED);
    }

    public function test_document_uploaded_triggers_first_document_milestone(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user);
        $this->createMaintenanceLog($vehicle);

        $this->createDocument($vehicle);

        $this->assertMilestone($user, LifecycleMilestone::FIRST_DOCUMENT);
        $this->assertCurrentState($user, LifecycleState::DOCUMENTS_ADDED);
    }

    public function test_fuel_log_created_triggers_first_fuel_log_milestone(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 120,
            'fuel_liters' => 8,
        ]);

        $this->assertMilestone($user, LifecycleMilestone::FIRST_FUEL_LOG);
    }

    public function test_public_garage_activation_triggers_public_garage_milestone(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createCompleteVehicle($user, [
            'is_public' => false,
            'public_slug' => null,
        ]);
        $this->createMaintenanceLog($vehicle);
        $this->createDocument($vehicle);

        $vehicle->update([
            'is_public' => true,
            'public_slug' => 'kia-ceed',
        ]);

        $this->assertMilestone($user, LifecycleMilestone::PUBLIC_GARAGE);
        $this->assertCurrentState($user, LifecycleState::HEALTHY_GARAGE);
    }

    public function test_lifecycle_state_changed_event_is_dispatched_on_transition(): void
    {
        $user = User::factory()->create();
        app(LifecycleProgressSyncService::class)->syncUser($user);

        Event::fake([LifecycleStateChanged::class]);

        $this->createVehicle($user);

        Event::assertDispatched(LifecycleStateChanged::class, function (LifecycleStateChanged $event) use ($user): bool {
            return $event->user->is($user)
                && $event->fromState === LifecycleState::REGISTERED->value
                && $event->toState === LifecycleState::VEHICLE_ADDED->value;
        });
    }

    public function test_repeated_events_do_not_create_duplicate_milestones(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);

        $this->createMaintenanceLog($vehicle);
        $this->createMaintenanceLog($vehicle, ['description' => 'Tweede onderhoud']);

        $this->assertSame(1, LifecycleMilestoneEntry::query()
            ->where('user_id', $user->id)
            ->where('milestone', LifecycleMilestone::FIRST_MAINTENANCE->value)
            ->count());
    }

    public function test_lifecycle_observers_do_not_queue_or_send_lifecycle_mails(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create();
        $vehicle = $this->createVehicle($user);
        $this->createMaintenanceLog($vehicle);
        $this->createDocument($vehicle);
        FuelLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_date' => now()->toDateString(),
            'distance_km' => 120,
            'fuel_liters' => 8,
        ]);

        Mail::assertNothingSent();
        Mail::assertNotSent(LifecycleEmailMailable::class);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('lifecycle_email_logs', 0);
    }

    private function createVehicle(User $user, array $attributes = []): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kia',
            'model' => 'Ceed',
            ...$attributes,
        ]);
    }

    private function createCompleteVehicle(User $user, array $attributes = []): Vehicle
    {
        return $this->createVehicle($user, [
            'year' => 2021,
            'current_km' => 42_000,
            'license_plate' => 'K-123-AA',
            'photo' => 'vehicle-photos/kia.jpg',
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

    private function assertMilestone(User $user, LifecycleMilestone $milestone): void
    {
        $this->assertDatabaseHas('lifecycle_milestone_entries', [
            'user_id' => $user->id,
            'milestone' => $milestone->value,
        ]);
    }

    private function assertCurrentState(User $user, LifecycleState $state): void
    {
        $this->assertDatabaseHas('lifecycle_state_entries', [
            'user_id' => $user->id,
            'state' => $state->value,
            'exited_at' => null,
        ]);
    }
}
