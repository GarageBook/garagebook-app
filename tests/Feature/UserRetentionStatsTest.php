<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Widgets\UserRetentionStats;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRetentionStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_stats_calculate_activity_and_returning_users(): void
    {
        CarbonImmutable::setTestNow('2026-04-22 12:00:00');

        User::factory()->create([
            'email' => 'active-7@example.com',
            'first_login_at' => '2026-04-10 10:00:00',
            'last_login_at' => '2026-04-21 10:00:00',
        ]);

        User::factory()->create([
            'email' => 'active-30@example.com',
            'first_login_at' => '2026-04-01 10:00:00',
            'last_login_at' => '2026-04-05 10:00:00',
        ]);

        User::factory()->create([
            'email' => 'single-login@example.com',
            'first_login_at' => '2026-04-15 10:00:00',
            'last_login_at' => '2026-04-15 10:00:00',
        ]);

        User::factory()->create([
            'email' => 'never-login@example.com',
            'first_login_at' => null,
            'last_login_at' => null,
        ]);

        $stats = UserRetentionStats::calculateStats();

        $this->assertSame(1, $stats['active_last_7_days']);
        $this->assertSame(3, $stats['active_last_30_days']);
        $this->assertSame(2, $stats['returning_users']);
        $this->assertSame(25.0, $stats['active_last_7_days_rate']);
        $this->assertSame(75.0, $stats['active_last_30_days_rate']);

        CarbonImmutable::setTestNow();
    }
}
