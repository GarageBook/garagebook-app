<?php

namespace Tests\Feature;

use App\Filament\Widgets\GrowthKpiOverviewWidget;
use App\Filament\Widgets\GrowthProductActivationFunnelWidget;
use App\Filament\Widgets\GrowthSourceActivationWidget;
use App\Models\AnalyticsDailySummary;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\Growth\GrowthDashboardData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class GrowthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_growth_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/growth-dashboard')
            ->assertOk()
            ->assertSee('Growth dashboard')
            ->assertSee('KPI-overzicht')
            ->assertSee('Acquisitie')
            ->assertSee('SEO intelligence')
            ->assertSee('Funnel / activatie')
            ->assertSee('Activatie per bron');
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
        $admin = User::factory()->admin()->create();

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

    public function test_growth_kpis_show_clear_unavailable_state_when_analytics_summaries_are_empty(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(GrowthKpiOverviewWidget::class)
            ->assertSeeText('Bezoekers vandaag')
            ->assertSeeText('niet beschikbaar')
            ->assertSeeText('Registraties laatste 30 dagen');
    }

    public function test_growth_kpis_use_stale_latest_available_analytics_window_with_warning(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        try {
            $admin = User::factory()->admin()->create();

            AnalyticsDailySummary::query()->create([
                'date' => '2026-05-18',
                'users' => 21,
                'sessions' => 25,
                'screen_page_views' => 60,
                'event_count' => 100,
                'conversions' => 0,
            ]);

            AnalyticsDailySummary::query()->create([
                'date' => '2026-04-18',
                'users' => 999,
                'sessions' => 999,
                'screen_page_views' => 999,
                'event_count' => 999,
                'conversions' => 0,
            ]);

            $this->actingAs($admin);

            Livewire::test(GrowthKpiOverviewWidget::class)
                ->assertSeeText('Data t/m 18-05-2026')
                ->assertSeeText('Analytics-sync lijkt achter te lopen')
                ->assertSeeText('21')
                ->assertDontSeeText('999');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_growth_dashboard_shows_registration_kpis_with_existing_users(): void
    {
        $admin = User::factory()->admin()->create([
            'created_at' => now()->subDays(3),
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

    public function test_growth_funnel_counts_activation_and_retention_stats_correctly(): void
    {
        Carbon::setTestNow('2026-06-17 10:00:00');

        try {
            $admin = User::factory()->admin()->create([
                'created_at' => now()->subDays(60),
            ]);

            $userWithEverything = User::factory()->create([
                'created_at' => now()->subDays(5),
                'first_login_at' => now()->subDays(20),
                'last_login_at' => now()->subDays(5),
                'first_booklet_downloaded_at' => now()->subDays(2),
            ]);
            $vehicleOne = Vehicle::query()->create([
                'user_id' => $userWithEverything->id,
                'brand' => 'Honda',
                'model' => 'CB500',
                'is_public' => true,
            ]);
            MaintenanceLog::query()->create([
                'vehicle_id' => $vehicleOne->id,
                'description' => 'Service 1',
                'maintenance_date' => today(),
                'km_reading' => 1000,
                'reminder_enabled' => true,
                'interval_months' => 12,
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

            $userWithVehicleAndLog = User::factory()->create([
                'created_at' => now()->subDays(20),
                'last_login_at' => now()->subDays(2),
            ]);
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

            User::factory()->create([
                'created_at' => now()->subDays(2),
                'last_login_at' => now()->subDays(40),
            ]);

            $this->actingAs($admin);

            Livewire::test(GrowthProductActivationFunnelWidget::class)
                ->assertSeeText('Registraties 7 dagen')
                ->assertSeeText('3')
                ->assertSeeText('Registraties 30 dagen')
                ->assertSeeText('4')
                ->assertSeeText('Reminder actief')
                ->assertSeeText('1')
                ->assertSeeText('Boekje gedownload')
                ->assertSeeText('Publieke voertuigen')
                ->assertSeeText('Kernconversies')
                ->assertSeeText('Registratie → voertuig')
                ->assertSeeText('Voertuig → eerste onderhoudslog')
                ->assertSeeText('Eerste onderhoudslog → reminder actief')
                ->assertSeeText('Eerste onderhoudslog → onderhoudsboekje download')
                ->assertSeeText('50,0%');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_growth_funnel_counts_maintenance_logs_across_multiple_vehicles_for_one_user(): void
    {
        $admin = User::factory()->admin()->create();

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

    public function test_growth_dashboard_reports_activation_metrics_per_registration_source(): void
    {
        User::factory()->create([
            'registration_source' => null,
            'created_at' => now()->subDays(3),
        ]);

        $geratelUser = User::factory()->create([
            'registration_source' => 'geratel',
            'created_at' => now()->subDays(2),
        ]);
        $geratelVehicle = Vehicle::query()->create([
            'user_id' => $geratelUser->id,
            'brand' => 'Honda',
            'model' => 'CB500',
        ]);
        MaintenanceLog::query()->create([
            'vehicle_id' => $geratelVehicle->id,
            'description' => 'Geratel service',
            'maintenance_date' => today(),
            'km_reading' => 1000,
        ]);

        $partnerUser = User::factory()->create([
            'registration_source' => null,
            'created_at' => now()->subDay(),
        ]);
        Vehicle::query()->create([
            'user_id' => $partnerUser->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
        ]);
        DB::table('user_attributions')->insert([
            'user_id' => $partnerUser->id,
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'partner_slug' => 'motorclub-x',
            'landing_page' => '/start',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = app(GrowthDashboardData::class)->sourceActivation();
        $rows = collect($data['rows'])->keyBy('source');

        $this->assertSame(3, $data['totals']['registrations']);
        $this->assertSame(2, $data['totals']['users_with_vehicle']);
        $this->assertSame(1, $data['totals']['users_with_maintenance_log']);

        $this->assertSame(1, $rows['direct']['registrations']);
        $this->assertSame(0, $rows['direct']['users_with_vehicle']);
        $this->assertSame(0.0, $rows['direct']['activation_percentage']);

        $this->assertSame(1, $rows['geratel']['registrations']);
        $this->assertSame(1, $rows['geratel']['users_with_vehicle']);
        $this->assertSame(1, $rows['geratel']['users_with_maintenance_log']);
        $this->assertSame(100.0, $rows['geratel']['activation_percentage']);

        $this->assertSame(1, $rows['partner']['registrations']);
        $this->assertSame(1, $rows['partner']['users_with_vehicle']);
        $this->assertSame('club2026', $rows['partner']['campaigns']);
        $this->assertSame('motorclub-x', $rows['partner']['partners']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(GrowthSourceActivationWidget::class)
            ->assertSeeText('Activatie per bron')
            ->assertSeeText('geratel')
            ->assertSeeText('direct')
            ->assertSeeText('partner')
            ->assertSeeText('club2026');
    }

    public function test_existing_analytics_dashboard_remains_reachable(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/analytics-dashboard')
            ->assertOk()
            ->assertSee('Analytics dashboard');
    }
}
