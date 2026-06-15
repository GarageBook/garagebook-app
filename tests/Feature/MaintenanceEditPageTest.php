<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\MaintenanceLogs\Pages\EditMaintenanceLog;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceEditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_loads_for_log_with_media(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'current_km' => 12000,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud',
            'km_reading' => 12000,
            'maintenance_date' => now()->toDateString(),
            'media_attachments' => [
                'maintenance-attachments/a.jpg',
                'maintenance-attachments/b.mov',
            ],
        ]);

        $this->actingAs($user)
            ->get("/admin/maintenance-logs/{$log->id}/edit")
            ->assertOk()
            ->assertSeeText("Foto's, video's en bestanden")
            ->assertSeeText('Onderhoud verwijderen')
            ->assertSee('maintenance-attachments/a.jpg', false)
            ->assertSee('maintenance-attachments/b.mov', false);
    }

    public function test_edit_save_redirects_to_maintenance_index(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'current_km' => 18000,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Oude omschrijving',
            'km_reading' => 18000,
            'maintenance_date' => '2026-06-10',
        ]);

        $this->actingAs($user);

        Livewire::test(EditMaintenanceLog::class, ['record' => $log->getKey()])
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Nieuwe omschrijving',
                'km_reading' => 18100,
                'maintenance_date' => '2026-06-15',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(MaintenanceLogResource::getUrl('index'));

        $this->assertDatabaseHas('maintenance_logs', [
            'id' => $log->id,
            'description' => 'Nieuwe omschrijving',
            'km_reading' => 18100,
        ]);

        Bus::assertDispatched(OptimizeMaintenanceLogMedia::class);
    }
}
