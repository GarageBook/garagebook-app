<?php

namespace Tests\Feature;

use App\Filament\Resources\Vehicles\Tables\VehiclesTable;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehiclesTableFileCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_count_includes_vehicle_and_maintenance_files(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 55000,
            'photo' => 'vehicle-attachments/main.png',
            'photos' => ['vehicle-attachments/gallery-1.jpg'],
            'media_attachments' => ['vehicle-attachments/video.mov'],
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud 1',
            'km_reading' => 54000,
            'maintenance_date' => '2026-04-22',
            'attachments' => [
                'maintenance-attachments/a.jpg',
                'maintenance-attachments/b.pdf',
            ],
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud 2',
            'km_reading' => 54500,
            'maintenance_date' => '2026-04-23',
            'attachments' => [
                'maintenance-attachments/c.mov',
            ],
        ]);

        $vehicle->load('maintenanceLogs');

        $this->assertSame(6, VehiclesTable::fileCount($vehicle));
    }
}
