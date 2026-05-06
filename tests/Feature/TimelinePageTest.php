<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelinePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_only_the_selected_vehicles_timeline(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);
        $otherUser = User::factory()->create();

        $selectedVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Tourfiets',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $foreignVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'BMW',
            'model' => 'R 1200 GS',
            'nickname' => 'Allroad',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $selectedVehicle->id,
            'description' => 'Kleine beurt',
            'km_reading' => 12000,
            'maintenance_date' => '2026-01-15',
            'cost' => 149.95,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Nieuwe remblokken',
            'km_reading' => 22000,
            'maintenance_date' => '2026-02-15',
            'cost' => 89.95,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $foreignVehicle->id,
            'description' => 'Bandenwissel',
            'km_reading' => 30000,
            'maintenance_date' => '2026-03-15',
            'cost' => 320.00,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn?vehicle_id=' . $selectedVehicle->id)
            ->assertOk()
            ->assertSeeText('Tijdlijn')
            ->assertSeeText('Tourfiets')
            ->assertSeeText('Kleine beurt')
            ->assertDontSeeText('Nieuwe remblokken')
            ->assertDontSeeText('Bandenwissel');
    }
}
