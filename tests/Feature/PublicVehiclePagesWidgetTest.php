<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\PublicVehiclePagesWidget;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicVehiclePagesWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widget_shows_public_vehicles_with_view_and_copy_actions(): void
    {
        $user = User::factory()->create();

        $publicVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander',
            'nickname' => 'Gezinsauto',
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $publicVehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12000,
            'maintenance_date' => now()->toDateString(),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
            'is_public' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(PublicVehiclePagesWidget::class)
            ->assertSee('Publieke voertuigpagina\'s', false)
            ->assertSee('Gezinsauto')
            ->assertSee('1 onderhoudslog')
            ->assertSee('Bekijken')
            ->assertSee('Kopieer link')
            ->assertSee('data-analytics-event="public_vehicle_page_view_clicked"', false)
            ->assertSee('data-analytics-event="public_vehicle_page_link_copied"', false);

        $events = app(AnalyticsEventTracker::class)->consume();
        $this->assertSame('public_vehicle_dashboard_widget_viewed', $events[0]['name'] ?? null);
        $this->assertSame(1, $events[0]['params']['public_vehicle_count'] ?? null);
    }

    public function test_dashboard_widget_shows_activation_cta_when_user_has_no_public_vehicle(): void
    {
        $user = User::factory()->create();

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
            'is_public' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(PublicVehiclePagesWidget::class)
            ->assertSee('Je hebt nog geen openbare voertuigpagina')
            ->assertSee('Zet je eerste publieke voertuigpagina live')
            ->assertDontSee('Bekijken');
    }

    public function test_dashboard_page_renders_public_vehicle_widget_when_user_has_vehicle(): void
    {
        $user = User::factory()->create();

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
            'is_public' => false,
        ]);

        $this->actingAs($user)
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('Publieke voertuigpagina\'s', false);
    }
}
