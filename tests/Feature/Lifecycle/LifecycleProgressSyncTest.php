<?php

namespace Tests\Feature\Lifecycle;

use App\Enums\LifecycleMilestone;
use App\Enums\LifecycleState;
use App\Mail\Lifecycle\LifecycleEmailMailable;
use App\Models\LifecycleMilestoneEntry;
use App\Models\LifecycleStateEntry;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Lifecycle\LifecycleProgressSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleProgressSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_entry_is_created(): void
    {
        $user = User::factory()->create();

        app(LifecycleProgressSyncService::class)->syncUser($user);

        $this->assertDatabaseHas('lifecycle_state_entries', [
            'user_id' => $user->id,
            'state' => LifecycleState::REGISTERED->value,
            'exited_at' => null,
        ]);
    }

    public function test_state_transition_closes_previous_state(): void
    {
        $user = User::factory()->create();
        app(LifecycleProgressSyncService::class)->syncUser($user, now()->subDay());

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kia',
            'model' => 'Ceed',
        ]);

        app(LifecycleProgressSyncService::class)->syncUser($user, now());

        $this->assertDatabaseHas('lifecycle_state_entries', [
            'user_id' => $user->id,
            'state' => LifecycleState::REGISTERED->value,
        ]);
        $this->assertNotNull(LifecycleStateEntry::query()
            ->where('user_id', $user->id)
            ->where('state', LifecycleState::REGISTERED->value)
            ->first()?->exited_at);
        $this->assertDatabaseHas('lifecycle_state_entries', [
            'user_id' => $user->id,
            'state' => LifecycleState::VEHICLE_ADDED->value,
            'exited_at' => null,
        ]);
    }

    public function test_milestone_is_recorded_once(): void
    {
        $user = User::factory()->create();
        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kia',
            'model' => 'Ceed',
        ]);

        app(LifecycleProgressSyncService::class)->syncUser($user);
        app(LifecycleProgressSyncService::class)->syncUser($user);

        $this->assertSame(1, LifecycleMilestoneEntry::query()
            ->where('user_id', $user->id)
            ->where('milestone', LifecycleMilestone::FIRST_VEHICLE->value)
            ->count());
    }

    public function test_command_is_idempotent(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kia',
            'model' => 'Ceed',
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud',
            'km_reading' => 12345,
            'maintenance_date' => now()->toDateString(),
        ]);

        $this->artisan('garagebook:lifecycle:sync-states', ['--chunk' => 1])->assertSuccessful();
        $this->artisan('garagebook:lifecycle:sync-states', ['--chunk' => 1])->assertSuccessful();

        $this->assertSame(1, LifecycleStateEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('exited_at')
            ->count());
        $this->assertSame(2, LifecycleMilestoneEntry::query()->where('user_id', $user->id)->count());
    }

    public function test_zero_state_user_works(): void
    {
        $user = User::factory()->create();

        $this->artisan('garagebook:lifecycle:sync-states')->assertSuccessful();

        $this->assertDatabaseHas('lifecycle_state_entries', [
            'user_id' => $user->id,
            'state' => LifecycleState::REGISTERED->value,
            'exited_at' => null,
        ]);
        $this->assertSame(0, LifecycleMilestoneEntry::query()->where('user_id', $user->id)->count());
    }

    public function test_sync_does_not_queue_or_send_lifecycle_mails(): void
    {
        Mail::fake();
        Queue::fake();

        User::factory()->create();

        $this->artisan('garagebook:lifecycle:sync-states')->assertSuccessful();

        Mail::assertNothingSent();
        Mail::assertNotSent(LifecycleEmailMailable::class);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('lifecycle_email_logs', 0);
    }
}
