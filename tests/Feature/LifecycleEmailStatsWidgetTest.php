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

    public function test_lifecycle_email_stats_widget_reports_sent_and_outstanding_counts(): void
    {
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
        ]);

        $day3User = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);
        Vehicle::query()->create([
            'user_id' => $day3User->id,
            'brand' => 'Honda',
            'model' => 'CB500X',
        ]);

        $day14User = User::factory()->create([
            'created_at' => now()->subDays(14),
        ]);
        Vehicle::query()->create([
            'user_id' => $day14User->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
        ]);

        $day30User = User::factory()->create([
            'created_at' => now()->subDays(30),
        ]);
        Vehicle::query()->create([
            'user_id' => $day30User->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom',
        ]);

        $stats = LifecycleEmailStatsWidget::calculateStats();

        $this->assertSame(1, $stats['sent_last_30_days']);
        $this->assertSame(1, $stats['outstanding_day_3']);
        $this->assertSame(1, $stats['outstanding_day_14']);
        $this->assertSame(1, $stats['outstanding_day_30']);
    }
}
