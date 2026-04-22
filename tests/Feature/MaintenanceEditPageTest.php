<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSeeText('Onderhoud verwijderen');
    }
}
