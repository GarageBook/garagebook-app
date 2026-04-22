<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_maintenance_pdf_with_non_string_attachments(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12345,
            'maintenance_date' => now()->toDateString(),
            'attachments' => [
                ['url' => 'https://files.example.test/maintenance-note.pdf'],
                'maintenance-attachments/factuur.pdf',
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/maintenance/pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader(
            'content-disposition',
            'attachment; filename=circuitfiets-onderhoud.pdf'
        );
    }
}
