<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceLogWorkedHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_log_can_store_worked_hours(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500',
            'current_km' => 12000,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Klepcontrole',
            'km_reading' => 12000,
            'maintenance_date' => '2026-04-22',
            'cost' => 150,
            'worked_hours' => 2.5,
        ]);

        $this->assertSame('2.50', $log->fresh()->worked_hours);
    }
}
