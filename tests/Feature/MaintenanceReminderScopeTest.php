<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Filament\Widgets\MaintenanceReminders;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceReminderScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_reminders_are_limited_to_the_authenticated_users_vehicles(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 12000,
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'current_km' => 9000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'description' => 'Nieuwe olie Motul 300V 15W50 met lang oliefilter',
            'km_reading' => 12000,
            'maintenance_date' => now()->subMonths(9)->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 6,
        ]);

        $visibleLog = MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Eigen onderhoud',
            'km_reading' => 9000,
            'maintenance_date' => now()->subMonth()->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 6,
        ]);

        $this->actingAs($otherUser);

        $reminders = app(ReminderService::class)->getWidgetItems();

        $this->assertCount(1, $reminders);
        $this->assertTrue($visibleLog->is($reminders[0]['log']));
        $this->assertSame('Eigen onderhoud', $reminders[0]['log']->description);
    }

    public function test_dashboard_widget_does_not_render_another_users_reminders(): void
    {
        $owner = User::factory()->create();
        $newUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 12000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'description' => 'Nieuwe olie Motul 300V 15W50 met lang oliefilter',
            'km_reading' => 12000,
            'maintenance_date' => now()->subMonths(9)->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 6,
        ]);

        $this->actingAs($newUser);

        Livewire::test(MaintenanceReminders::class)
            ->assertSeeText('Toekomstig onderhoud')
            ->assertSeeText('Geen aankomende onderhoudsmomenten')
            ->assertDontSeeText('Aprilia RSV Mille')
            ->assertDontSeeText('Nieuwe olie Motul 300V 15W50 met lang oliefilter');
    }

    public function test_user_without_vehicles_never_gets_reminders(): void
    {
        $owner = User::factory()->create();
        $newUser = User::factory()->create();

        $ownerVehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 12000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownerVehicle->id,
            'description' => 'Nieuwe banden Pirelli Diablo Corsa 3',
            'km_reading' => 12000,
            'maintenance_date' => now()->subYear()->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 6,
        ]);

        $this->actingAs($newUser);

        $this->assertSame([], app(ReminderService::class)->getWidgetItems());
    }
}
