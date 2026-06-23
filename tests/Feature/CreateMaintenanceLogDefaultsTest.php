<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\MaintenanceLogs\Pages\CreateMaintenanceLog;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class CreateMaintenanceLogDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_defaults_to_requested_owned_vehicle(): void
    {
        $user = User::factory()->create();

        $olderVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500X',
        ]);

        $requestedVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $olderVehicle->id,
            'description' => 'Oud onderhoud',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['vehicle_id' => $requestedVehicle->id])
            ->test(CreateMaintenanceLog::class)
            ->assertSet('data.vehicle_id', $requestedVehicle->id)
            ->assertSet('data.maintenance_date', now()->toDateString())
            ->assertSeeText("Begin simpel. Je kunt later altijd foto's, facturen of details toevoegen.")
            ->assertSee('option value="Olie + filter vervangen"', false)
            ->assertSee('option value="Algemene controle"', false);
    }

    public function test_create_form_defaults_to_vehicle_with_latest_maintenance_without_vehicle_context(): void
    {
        $user = User::factory()->create();

        $fallbackVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
        ]);

        $latestMaintenanceVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $fallbackVehicle->id,
            'description' => 'Ouder onderhoud',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDays(10)->toDateString(),
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $latestMaintenanceVehicle->id,
            'description' => 'Recent onderhoud',
            'km_reading' => 22000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->assertSet('data.vehicle_id', $latestMaintenanceVehicle->id);
    }

    public function test_create_form_never_defaults_to_other_users_vehicle(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom 650',
        ]);

        $otherUsersVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'KTM',
            'model' => '890 Adventure',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownVehicle->id,
            'description' => 'Eigen onderhoud',
            'km_reading' => 18000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherUsersVehicle->id,
            'description' => 'Andermans onderhoud',
            'km_reading' => 9000,
            'maintenance_date' => now()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['vehicle_id' => $otherUsersVehicle->id])
            ->test(CreateMaintenanceLog::class)
            ->assertSet('data.vehicle_id', $ownVehicle->id);
    }

    public function test_create_redirects_to_maintenance_index_after_save(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Ducati',
            'model' => 'Multistrada V4',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Olie vervangen',
                'km_reading' => 15100,
                'maintenance_date' => '2026-06-15',
                'cost' => '149.95',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(MaintenanceLogResource::getUrl('index'));

        $this->assertDatabaseHas('maintenance_logs', [
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
        ]);

        Bus::assertDispatched(OptimizeMaintenanceLogMedia::class);
    }

    public function test_create_public_vehicle_maintenance_log_notifies_that_public_page_was_updated(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander',
            'is_public' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Olie vervangen',
                'km_reading' => 15100,
                'maintenance_date' => '2026-06-15',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Onderhoud toegevoegd. Je publieke voertuigpagina is bijgewerkt.')
            ->assertRedirect(MaintenanceLogResource::getUrl('index'));
    }

    public function test_first_maintenance_log_can_be_saved_with_minimal_required_fields(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Kawasaki',
            'model' => 'Versys 650',
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['vehicle_id' => $vehicle->id, 'onboarding' => 1])
            ->test(CreateMaintenanceLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Algemene controle',
                'km_reading' => 18450,
                'maintenance_date' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(MaintenanceLogResource::getUrl('index'));

        $this->assertDatabaseHas('maintenance_logs', [
            'vehicle_id' => $vehicle->id,
            'description' => 'Algemene controle',
            'km_reading' => 18450,
        ]);
    }

    public function test_mutate_form_data_uses_fallback_vehicle_when_vehicle_id_is_missing(): void
    {
        $user = User::factory()->create();

        $fallbackVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Triumph',
            'model' => 'Tiger 900',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $fallbackVehicle->id,
            'description' => 'Bestaand onderhoud',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        $component = Livewire::test(CreateMaintenanceLog::class);
        $method = new ReflectionMethod($component->instance(), 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $result = $method->invoke($component->instance(), [
            'distance_unit' => 'km',
            'description' => 'Nieuw onderhoud',
            'km_reading' => 12100,
            'maintenance_date' => '2026-06-15',
        ]);

        $this->assertSame($fallbackVehicle->id, $result['vehicle_id']);
    }
}
