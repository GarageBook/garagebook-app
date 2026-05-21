<?php

namespace Tests\Feature;

use App\Filament\Widgets\GrowthKpiOverviewWidget;
use App\Filament\Widgets\GrowthProductActivationFunnelWidget;
use App\Models\AnalyticsDailySummary;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_growth_dashboard(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/growth-dashboard')
            ->assertOk()
            ->assertSee('Growth dashboard')
            ->assertSee('KPI-overzicht')
            ->assertSee('Acquisitie')
            ->assertSee('SEO intelligence')
            ->assertSee('Funnel / activatie');
    }

    public function test_non_admin_cannot_open_growth_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/growth-dashboard')
            ->assertForbidden();
    }

    public function test_growth_dashboard_does_not_crash_without_analytics_data(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        Schema::dropIfExists('analytics_daily_summaries');
        Schema::dropIfExists('analytics_top_pages');
        Schema::dropIfExists('search_console_daily_summaries');
        Schema::dropIfExists('search_console_queries');
        Schema::dropIfExists('search_console_pages');

        $this->actingAs($admin)
            ->get('/admin/growth-dashboard')
            ->assertOk()
            ->assertSee('Growth dashboard')
            ->assertSee('niet beschikbaar')
            ->assertSee('Nog geen attributionregistraties beschikbaar.');
    }

    public function test_growth_dashboard_shows_registration_kpis_with_existing_users(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'created_at' => now()->subDays(60),
        ]);

        AnalyticsDailySummary::query()->create([
            'date' => today(),
            'users' => 17,
            'sessions' => 21,
            'screen_page_views' => 50,
            'event_count' => 99,
            'conversions' => 0,
        ]);

        User::factory()->create([
            'name' => 'Growth Old',
            'created_at' => now()->subDays(40),
        ]);

        User::factory()->create([
            'name' => 'Growth Week',
            'created_at' => now()->subDays(5),
        ]);

        User::factory()->create([
            'name' => 'Growth Latest',
            'created_at' => now()->subHour(),
        ]);

        $this->actingAs($admin);

        Livewire::test(GrowthKpiOverviewWidget::class)
            ->assertSeeText('Bezoekers vandaag')
            ->assertSeeText('17')
            ->assertSeeText('Registraties laatste 30 dagen')
            ->assertSeeText('2')
            ->assertSeeText('Growth Latest');
    }

    public function test_growth_funnel_counts_users_with_vehicles_and_maintenance_logs_correctly(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $userWithEverything = User::factory()->create([
            'first_login_at' => now()->subDays(20),
            'last_login_at' => now()->subDays(5),
        ]);
        $vehicleOne = Vehicle::query()->create([
            'user_id' => $userWithEverything->id,
            'brand' => 'Honda',
            'model' => 'CB500',
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicleOne->id,
            'description' => 'Service 1',
            'maintenance_date' => today(),
            'km_reading' => 1000,
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicleOne->id,
            'description' => 'Service 2',
            'maintenance_date' => today(),
            'km_reading' => 1001,
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicleOne->id,
            'description' => 'Service 3',
            'maintenance_date' => today(),
            'km_reading' => 1002,
        ]);
        VehicleDocument::query()->create([
            'vehicle_id' => $vehicleOne->id,
            'title' => 'Invoice',
            'document_type' => 'invoice',
            'file_path' => 'docs/invoice.pdf',
            'original_filename' => 'invoice.pdf',
        ]);
        FuelLog::query()->create([
            'vehicle_id' => $vehicleOne->id,
            'fuel_date' => today(),
            'odometer_km' => 1000,
            'distance_km' => 120,
            'fuel_liters' => 7.5,
        ]);

        $userWithVehicleAndLog = User::factory()->create();
        $vehicleTwo = Vehicle::query()->create([
            'user_id' => $userWithVehicleAndLog->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicleTwo->id,
            'description' => 'Single service',
            'maintenance_date' => today(),
            'km_reading' => 1500,
        ]);

        User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(GrowthProductActivationFunnelWidget::class)
            ->assertSeeText('Totaal users')
            ->assertSeeText('4')
            ->assertSeeText('Users met minimaal 1 voertuig')
            ->assertSeeText('2')
            ->assertSeeText('Users met minimaal 3 maintenance logs')
            ->assertSeeText('1')
            ->assertSeeText('Users met minimaal 1 document/upload')
            ->assertSeeText('Users met fuel entries')
            ->assertSeeText('Teruggekomen na 7 dagen');
    }

    public function test_growth_funnel_counts_maintenance_logs_across_multiple_vehicles_for_one_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $multiVehicleUser = User::factory()->create();

        $firstVehicle = Vehicle::query()->create([
            'user_id' => $multiVehicleUser->id,
            'brand' => 'BMW',
            'model' => 'F 900 R',
        ]);

        $secondVehicle = Vehicle::query()->create([
            'user_id' => $multiVehicleUser->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $firstVehicle->id,
            'description' => 'Service A',
            'maintenance_date' => today(),
            'km_reading' => 1000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $secondVehicle->id,
            'description' => 'Service B',
            'maintenance_date' => today(),
            'km_reading' => 2000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $secondVehicle->id,
            'description' => 'Service C',
            'maintenance_date' => today(),
            'km_reading' => 3000,
        ]);

        $this->actingAs($admin);

        Livewire::test(GrowthProductActivationFunnelWidget::class)
            ->assertSeeText('Users met minimaal 3 maintenance logs')
            ->assertSeeText('1');
    }

    public function test_existing_analytics_dashboard_remains_reachable(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/analytics-dashboard')
            ->assertOk()
            ->assertSee('Analytics dashboard');
    }
}
