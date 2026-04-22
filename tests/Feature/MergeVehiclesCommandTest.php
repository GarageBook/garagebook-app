<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeVehiclesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_command_moves_maintenance_and_preserves_target_airtable_vehicle(): void
    {
        $user = User::factory()->create();

        $source = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'nickname' => 'Aprilia RSV Mille Alitalia',
            'license_plate' => 'MG-XS-98',
            'current_km' => 55555,
            'photo' => 'vehicle-attachments/source.png',
            'photos' => ['vehicle-attachments/source-2.jpg'],
            'notes' => 'Oude handmatige vehicle',
        ]);

        $target = Vehicle::query()->create([
            'user_id' => $user->id,
            'airtable_record_id' => 'recy4TWHFSkHVGl20',
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'nickname' => 'Aprilia RSV Mille Alitalia',
            'license_plate' => 'MG-XS-98',
            'current_km' => 55000,
            'photo' => 'vehicle-attachments/target.png',
            'photos' => ['vehicle-attachments/target-2.jpg'],
            'media_attachments' => ['vehicle-attachments/video.mov'],
            'notes' => 'Geimporteerd Airtable vehicle',
        ]);

        $sourceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $source->id,
            'description' => 'Bestaand onderhoud',
            'km_reading' => 55555,
            'maintenance_date' => '2026-04-01',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $target->id,
            'description' => 'Airtable onderhoud',
            'km_reading' => 55000,
            'maintenance_date' => '2026-04-02',
        ]);

        $this->artisan('vehicles:merge', [
            '--from' => (string) $source->id,
            '--into' => (string) $target->id,
            '--force' => true,
            '--delete-source' => true,
        ])->assertSuccessful();

        $target = $target->fresh();

        $this->assertSame('recy4TWHFSkHVGl20', $target->airtable_record_id);
        $this->assertSame(55555, $target->current_km);
        $this->assertSame('vehicle-attachments/target.png', $target->photo);
        $this->assertContains('vehicle-attachments/target-2.jpg', $target->photos ?? []);
        $this->assertContains('vehicle-attachments/source-2.jpg', $target->photos ?? []);
        $this->assertStringContainsString('Oude handmatige vehicle', $target->notes ?? '');
        $this->assertSame($target->id, $sourceLog->fresh()->vehicle_id);
        $this->assertDatabaseMissing('vehicles', [
            'id' => $source->id,
        ]);
    }
}
