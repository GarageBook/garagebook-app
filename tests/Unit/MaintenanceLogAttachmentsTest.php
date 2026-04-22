<?php

namespace Tests\Unit;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceLogAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_attachments_are_split_into_media_and_files(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'MT-07',
            'current_km' => 10000,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Legacy onderhoud',
            'km_reading' => 10000,
            'maintenance_date' => '2026-04-22',
            'attachments' => [
                'maintenance-attachments/a.jpg',
                'maintenance-attachments/b.mov',
                'maintenance-attachments/c.pdf',
            ],
        ]);

        $log->refresh();

        $this->assertSame([
            'maintenance-attachments/a.jpg',
            'maintenance-attachments/b.mov',
        ], $log->media_attachments);

        $this->assertSame([
            'maintenance-attachments/c.pdf',
        ], $log->file_attachments);

        $this->assertSame([
            'maintenance-attachments/a.jpg',
            'maintenance-attachments/b.mov',
            'maintenance-attachments/c.pdf',
        ], $log->attachments);
    }

    public function test_separated_attachments_still_expose_combined_legacy_accessor(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Africa Twin',
            'current_km' => 20000,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Gesplitst onderhoud',
            'km_reading' => 20000,
            'maintenance_date' => '2026-04-22',
            'media_attachments' => [
                'maintenance-attachments/a.jpg',
            ],
            'file_attachments' => [
                'maintenance-attachments/b.pdf',
            ],
        ]);

        $log->refresh();

        $this->assertSame([
            'maintenance-attachments/a.jpg',
        ], $log->media_attachments);

        $this->assertSame([
            'maintenance-attachments/b.pdf',
        ], $log->file_attachments);

        $this->assertSame([
            'maintenance-attachments/a.jpg',
            'maintenance-attachments/b.pdf',
        ], $log->attachments);
    }
}
