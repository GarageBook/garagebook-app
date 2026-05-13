<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Filament\Widgets\MaintenanceReminders;
use App\Services\DistanceUnitService;
use App\Services\ReminderService;
use Carbon\Carbon;
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
            ->assertSeeText('Voeg eerst een voertuig en onderhoudslogs toe.')
            ->assertDontSeeText('Aprilia RSV Mille')
            ->assertDontSeeText('Nieuwe olie Motul 300V 15W50 met lang oliefilter');
    }

    public function test_dashboard_widget_explains_empty_state_when_user_has_vehicles_but_no_maintenance_logs(): void
    {
        $user = User::factory()->create();

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V85 TT',
            'current_km' => 5000,
        ]);

        $this->actingAs($user);

        Livewire::test(MaintenanceReminders::class)
            ->assertSeeText('Toekomstig onderhoud')
            ->assertSeeText('Geen aankomende onderhoudsmomenten')
            ->assertSeeText('Voeg eerst een voertuig en onderhoudslogs toe.');
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

    public function test_reminder_service_combines_date_and_km_into_actionable_upcoming_text(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 14900,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie en oliefilter vervangen',
            'km_reading' => 12000,
            'maintenance_date' => now()->subMonths(11)->subWeeks(1)->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 12,
            'interval_km' => 3000,
        ]);

        $status = app(ReminderService::class)->getStatus($log);

        $this->assertSame('upcoming', $status['type']);
        $this->assertSame('Olie en oliefilter vervangen', $status['heading']);
        $this->assertStringStartsWith('Over ', $status['text']);
        $this->assertStringContainsString('100 km', $status['text']);
    }

    public function test_overdue_reminders_are_sorted_ahead_of_upcoming_items(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 18000,
        ]);

        $upcoming = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Koelvloeistof verversen',
            'km_reading' => 15000,
            'maintenance_date' => now()->subMonths(10)->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 12,
        ]);

        $overdue = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie en filter',
            'km_reading' => 12000,
            'maintenance_date' => now()->subMonths(15)->toDateString(),
            'reminder_enabled' => true,
            'interval_months' => 12,
        ]);

        $this->actingAs($user);

        $items = app(ReminderService::class)->getWidgetItems();

        $this->assertCount(2, $items);
        $this->assertTrue($overdue->is($items[0]['log']));
        $this->assertTrue($upcoming->is($items[1]['log']));
    }

    public function test_reminder_service_formats_distance_labels_in_miles_for_miles_vehicle(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Indian',
            'model' => 'Scout',
            'current_km' => 159325,
            'distance_unit' => DistanceUnitService::UNIT_MILES,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie verversen',
            'km_reading' => 152888,
            'maintenance_date' => now()->subMonths(11)->toDateString(),
            'reminder_enabled' => true,
            'interval_km' => 16093,
        ]);

        $status = app(ReminderService::class)->getStatus($log);

        $this->assertStringContainsString('6.000 mi', $status['text']);
    }

    public function test_reminder_service_rounds_upcoming_date_text_to_a_single_unit(): void
    {
        Carbon::setTestNow('2026-05-07');

        try {
            $user = User::factory()->create();

            $vehicle = Vehicle::query()->create([
                'user_id' => $user->id,
                'brand' => 'Yamaha',
                'model' => 'Tenere 700',
                'current_km' => 10000,
            ]);

            $log = MaintenanceLog::query()->create([
                'vehicle_id' => $vehicle->id,
                'description' => 'Klepcontrole',
                'km_reading' => 10000,
                'maintenance_date' => '2025-08-11',
                'reminder_enabled' => true,
                'interval_months' => 12,
            ]);

            $status = app(ReminderService::class)->getStatus($log);

            $this->assertSame('Over 3 maanden.', $status['text']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
