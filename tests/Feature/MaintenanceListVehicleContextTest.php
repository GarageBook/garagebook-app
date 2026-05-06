<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceLogs\Pages\ListMaintenanceLogs;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class MaintenanceListVehicleContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_list_filters_logs_to_the_selected_vehicle(): void
    {
        $user = User::factory()->create();

        $firstVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $secondVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Tourfiets',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $firstVehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDays(2)->toDateString(),
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $secondVehicle->id,
            'description' => 'Ketting gespannen',
            'km_reading' => 8000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['vehicle_id' => $firstVehicle->id])
            ->test(ListMaintenanceLogs::class)
            ->assertSet('activeVehicleId', $firstVehicle->id)
            ->assertSeeText('Circuitfiets')
            ->assertSeeText('Olie vervangen')
            ->assertDontSeeText('Ketting gespannen');
    }

    public function test_maintenance_list_defaults_to_vehicle_with_most_recent_maintenance(): void
    {
        $user = User::factory()->create();

        $olderVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $latestVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Tourfiets',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $olderVehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDays(10)->toDateString(),
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $latestVehicle->id,
            'description' => 'Ketting gespannen',
            'km_reading' => 8000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::test(ListMaintenanceLogs::class)
            ->assertSet('activeVehicleId', $latestVehicle->id)
            ->assertSeeText('Tourfiets')
            ->assertSeeText('Ketting gespannen')
            ->assertDontSeeText('Olie vervangen');
    }

    public function test_maintenance_list_header_actions_follow_the_selected_vehicle(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem Garage',
        ]);

        $firstVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $secondVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'nickname' => 'Allroad',
        ]);

        $this->actingAs($user);

        $component = Livewire::withQueryParams(['vehicle_id' => $secondVehicle->id])
            ->test(ListMaintenanceLogs::class);

        $method = new ReflectionMethod($component->instance(), 'getHeaderActions');
        $method->setAccessible(true);
        $actions = collect($method->invoke($component->instance()))->keyBy(fn ($action) => $action->getName());

        $this->assertSame(
            url('/share/willem-garage/allroad'),
            $actions->get('openSharePage')->getUrl(),
        );
        $this->assertSame(
            url('/maintenance/pdf?vehicle_id=' . $secondVehicle->id),
            $actions->get('exportPdf')->getUrl(),
        );
    }

    public function test_maintenance_list_header_actions_update_after_switching_vehicle(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem Garage',
        ]);

        $firstVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $secondVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'nickname' => 'Allroad',
        ]);

        $this->actingAs($user);

        $component = Livewire::withQueryParams(['vehicle_id' => $firstVehicle->id])
            ->test(ListMaintenanceLogs::class)
            ->set('activeVehicleId', $secondVehicle->id);

        $cachedActions = collect($component->instance()->getCachedHeaderActions())
            ->keyBy(fn ($action) => $action->getName());

        $this->assertSame(
            url('/share/willem-garage/allroad'),
            $cachedActions->get('openSharePage')->getUrl(),
        );
        $this->assertSame(
            url('/maintenance/pdf?vehicle_id=' . $secondVehicle->id),
            $cachedActions->get('exportPdf')->getUrl(),
        );
    }
}
