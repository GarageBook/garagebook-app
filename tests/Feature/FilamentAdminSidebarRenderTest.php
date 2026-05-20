<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\TripLogs\TripLogResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminSidebarRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_admin_routes_render_sidebar_without_crashing(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Eigen motor',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'nickname' => 'Andere motor',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $ownVehicle->id,
            'description' => 'Eigen onderhoud',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDay()->toDateString(),
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $otherVehicle->id,
            'description' => 'Andermans onderhoud',
            'km_reading' => 22000,
            'maintenance_date' => now()->toDateString(),
        ]);

        TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $ownVehicle->id,
            'title' => 'Eigen trip',
            'ridden_at' => now()->subDay()->toDateString(),
            'source_file_path' => 'trip-uploads/owner.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        TripLog::query()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => $otherVehicle->id,
            'title' => 'Verborgen trip',
            'ridden_at' => now()->toDateString(),
            'source_file_path' => 'trip-uploads/other.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();

        $this->actingAs($user)
            ->get(MaintenanceLogResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('Eigen motor')
            ->assertSeeText('Eigen onderhoud')
            ->assertDontSeeText('Andere motor')
            ->assertDontSeeText('Andermans onderhoud');

        $this->actingAs($user)
            ->get(TripLogResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('Eigen motor')
            ->assertSeeText('Eigen trip')
            ->assertDontSeeText('Andere motor')
            ->assertDontSeeText('Verborgen trip');

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('CBR600F')
            ->assertDontSeeText('R 1250 GS');
    }
}
