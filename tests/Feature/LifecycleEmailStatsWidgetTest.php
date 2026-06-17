<?php

namespace Tests\Feature;

use App\Filament\Widgets\LifecycleEmailStatsWidget;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleEmailStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LifecycleEmailTemplateSeeder::class);
    }

    public function test_lifecycle_email_stats_widget_reports_effectiveness_metrics_per_email_key(): void
    {
        $userWithVehicleNoMaintenance = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);

        Vehicle::query()->create([
            'user_id' => $userWithVehicleNoMaintenance->id,
            'brand' => 'Honda',
            'model' => 'CB500X',
        ]);

        $sentUser = User::factory()->create();
        Vehicle::query()->create([
            'user_id' => $sentUser->id,
            'brand' => 'BMW',
            'model' => 'R1250GS',
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $sentUser->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Verzonden',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now()->subDays(2),
            'clicked_at' => now()->subDay(),
            'goal_completed_at' => now()->subHours(12),
        ]);

        $queuedUser = User::factory()->create();
        Vehicle::query()->create([
            'user_id' => $queuedUser->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $queuedUser->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => 'Queued',
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        $failedUser = User::factory()->create();
        Vehicle::query()->create([
            'user_id' => $failedUser->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom',
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $failedUser->id,
            'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            'subject' => 'Failed',
            'status' => LifecycleEmailLog::STATUS_FAILED,
            'failed_at' => now()->subHour(),
        ]);

        $stats = LifecycleEmailStatsWidget::calculateStats();

        $this->assertSame(1, $stats['email_keys'][LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3]['sent']);
        $this->assertSame(1, $stats['email_keys'][LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3]['clicked']);
        $this->assertSame(1, $stats['email_keys'][LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3]['goal_completed']);
        $this->assertSame(1, $stats['email_keys'][LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14]['queued']);
        $this->assertSame(1, $stats['email_keys'][LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG]['failed']);
        $this->assertSame(4, $stats['users_with_vehicle_no_maintenance']);
    }
}
