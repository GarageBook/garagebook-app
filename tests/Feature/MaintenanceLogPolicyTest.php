<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MaintenanceLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_update_and_delete_own_maintenance_log(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Africa Twin',
            'current_km' => 22000,
        ]);

        $maintenanceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 22000,
            'maintenance_date' => '2026-05-20',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $maintenanceLog));
        $this->assertTrue(Gate::forUser($user)->allows('update', $maintenanceLog));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $maintenanceLog));
    }

    public function test_user_cannot_view_update_or_delete_another_users_maintenance_log(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom 650',
            'current_km' => 18000,
        ]);

        $maintenanceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Ketting gespannen',
            'km_reading' => 18000,
            'maintenance_date' => '2026-05-20',
        ]);

        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $maintenanceLog));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $maintenanceLog));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $maintenanceLog));
    }

    public function test_admin_bypass_applies_to_maintenance_log_policy(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Kawasaki',
            'model' => 'Versys 1000',
            'current_km' => 50000,
        ]);

        $maintenanceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Band vervangen',
            'km_reading' => 50000,
            'maintenance_date' => '2026-05-20',
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $maintenanceLog));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $maintenanceLog));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $maintenanceLog));
    }
}
