<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Widgets\UserActivationStats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivationWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_stats_calculate_totals_correctly(): void
    {
        User::factory()->create([
            'email' => 'active@example.com',
            'first_login_at' => now(),
        ]);

        User::factory()->create([
            'email' => 'inactive@example.com',
            'first_login_at' => null,
        ]);

        $stats = UserActivationStats::calculateStats();

        $this->assertSame(2, $stats['total_users']);
        $this->assertSame(1, $stats['activated_users']);
        $this->assertSame(1, $stats['inactive_users']);
        $this->assertSame(50.0, $stats['activation_rate']);
    }
}
