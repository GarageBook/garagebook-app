<?php

namespace Tests\Feature\Lifecycle;

use App\Mail\Lifecycle\LifecycleEmailMailable;
use App\Models\LifecycleRuleEvaluation;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleEngine;
use App\Services\Lifecycle\Rules\LifecycleRuleRegistry;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_vehicle_rule_matches_correctly(): void
    {
        $user = User::factory()->create();

        $winner = app(LifecycleRuleEngine::class)->evaluate($user)['winner'];

        $this->assertSame('no_vehicle', $winner?->ruleName);
    }

    public function test_first_maintenance_rule_matches_correctly(): void
    {
        $user = User::factory()->create();
        $this->createVehicle($user);

        $winner = app(LifecycleRuleEngine::class)->evaluate($user)['winner'];

        $this->assertSame('first_maintenance', $winner?->ruleName);
    }

    public function test_highest_priority_wins(): void
    {
        $user = User::factory()->create();
        $engine = new LifecycleRuleEngine(new class extends LifecycleRuleRegistry
        {
            public function rules(): Collection
            {
                return collect([
                    new FakeRule('low_priority', 10, true),
                    new FakeRule('high_priority', 99, true),
                ]);
            }
        });

        $winner = $engine->evaluate($user, persist: false)['winner'];

        $this->assertSame('high_priority', $winner?->ruleName);
    }

    public function test_cooldown_blocks_only_same_rule(): void
    {
        $user = User::factory()->create();
        LifecycleRuleEvaluation::query()->create([
            'user_id' => $user->id,
            'rule_name' => 'no_vehicle',
            'matched' => true,
            'reason' => 'recent match',
            'evaluated_at' => now()->subDay(),
            'cooldown_until' => now()->addDay(),
        ]);

        $engine = new LifecycleRuleEngine(new class extends LifecycleRuleRegistry
        {
            public function rules(): Collection
            {
                return collect([
                    new FakeRule('no_vehicle', 100, true, 2),
                    new FakeRule('first_maintenance', 90, true, 3),
                ]);
            }
        });

        $winner = $engine->evaluate($user, persist: false)['winner'];

        $this->assertSame('first_maintenance', $winner?->ruleName);
    }

    public function test_disabled_rule_is_ignored(): void
    {
        $user = User::factory()->create();
        $engine = new LifecycleRuleEngine(new class extends LifecycleRuleRegistry
        {
            public function rules(): Collection
            {
                return collect([
                    new FakeRule('disabled_high_priority', 100, false),
                    new FakeRule('enabled_low_priority', 10, true),
                ]);
            }
        });

        $winner = $engine->evaluate($user, persist: false)['winner'];

        $this->assertSame('enabled_low_priority', $winner?->ruleName);
    }

    public function test_command_queues_and_sends_nothing(): void
    {
        Mail::fake();
        Queue::fake();
        User::factory()->create();

        $this->artisan('garagebook:lifecycle:evaluate-rules')->assertSuccessful();

        Mail::assertNothingSent();
        Mail::assertNotSent(LifecycleEmailMailable::class);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('lifecycle_email_logs', 0);
    }

    public function test_command_is_idempotent_for_same_evaluation_window(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->startOfMinute());

        $this->artisan('garagebook:lifecycle:evaluate-rules')->assertSuccessful();
        $this->artisan('garagebook:lifecycle:evaluate-rules')->assertSuccessful();

        $this->assertSame(5, LifecycleRuleEvaluation::query()->where('user_id', $user->id)->count());
    }

    private function createVehicle(User $user): Vehicle
    {
        return Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kia',
            'model' => 'Ceed',
        ]);
    }

    private function createMaintenanceLog(Vehicle $vehicle): MaintenanceLog
    {
        return MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud',
            'km_reading' => 42_000,
            'maintenance_date' => now()->toDateString(),
        ]);
    }
}

class FakeRule implements LifecycleRule
{
    public function __construct(
        private readonly string $name,
        private readonly int $priority,
        private readonly bool $enabled,
        private readonly int $cooldownDays = 0,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function cooldownDays(): int
    {
        return $this->cooldownDays;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        return LifecycleRuleResult::match($this->name, 'fake match', $this->priority, $this->cooldownDays);
    }
}
