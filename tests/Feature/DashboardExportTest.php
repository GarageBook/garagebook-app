<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Pages\GrowthDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_analytics_data(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AnalyticsDashboard::class)
            ->callAction('export')
            ->assertFileDownloaded('analytics-export-' . now()->format('Y-m-d') . '.csv');
    }

    public function test_admin_can_export_growth_data(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(GrowthDashboard::class)
            ->callAction('export')
            ->assertFileDownloaded('growth-export-' . now()->format('Y-m-d') . '.csv');
    }

    public function test_regular_user_cannot_access_dashboards(): void
    {
        $user = User::factory()->create([
            'email' => 'regular@example.com',
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/analytics-dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/growth-dashboard')
            ->assertForbidden();
    }
}
