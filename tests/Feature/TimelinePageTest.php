<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\TripLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
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

        TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $selectedVehicle->id,
            'title' => 'Veluwerit',
            'ridden_at' => '2026-05-18',
            'source_file_path' => 'trip-uploads/selected.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $otherVehicle->id,
            'title' => 'Circuitdag',
            'ridden_at' => '2026-05-17',
            'source_file_path' => 'trip-uploads/other.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        TripLog::query()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => $foreignVehicle->id,
            'title' => 'Verborgen rit',
            'ridden_at' => '2026-05-16',
            'source_file_path' => 'trip-uploads/foreign.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn?vehicle_id=' . $selectedVehicle->id)
            ->assertOk()
            ->assertSeeText('Tijdlijn')
            ->assertSeeText('Tourfiets')
            ->assertSeeText('Kleine beurt')
            ->assertSeeText('Veluwerit')
            ->assertDontSeeText('Nieuwe remblokken')
            ->assertDontSeeText('Bandenwissel')
            ->assertDontSeeText('Circuitdag')
            ->assertDontSeeText('Verborgen rit');
    }

    public function test_timeline_defaults_to_vehicle_with_most_recent_maintenance(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $olderVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Circuitfiets',
        ]);

        $latestVehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'nickname' => 'Tourfiets',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $olderVehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 12000,
            'maintenance_date' => '2026-01-15',
            'cost' => 149.95,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $latestVehicle->id,
            'description' => 'Ketting gespannen',
            'km_reading' => 8000,
            'maintenance_date' => '2026-02-15',
            'cost' => 89.95,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn')
            ->assertOk()
            ->assertSeeText('Tourfiets')
            ->assertSeeText('Ketting gespannen')
            ->assertDontSeeText('Olie vervangen');
    }

    public function test_timeline_shows_miles_for_vehicle_that_uses_miles(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Harley-Davidson',
            'model' => 'Pan America',
            'nickname' => 'US Bike',
            'distance_unit' => DistanceUnitService::UNIT_MILES,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Servicebeurt',
            'km_reading' => 160934,
            'maintenance_date' => '2026-02-15',
            'cost' => 149.95,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertSeeText('100.000 mi');
    }

    public function test_timeline_integrates_trips_into_the_same_chronological_track(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'R 1300 GS',
            'nickname' => 'Reismotor',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Olie vervangen',
            'km_reading' => 10000,
            'maintenance_date' => '2026-01-01',
            'cost' => 99.00,
        ]);

        TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Veluwerit',
            'ridden_at' => '2026-01-02',
            'source_file_path' => 'trip-uploads/veluwe.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Banden vervangen',
            'km_reading' => 10300,
            'maintenance_date' => '2026-01-05',
            'cost' => 320.00,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertSeeTextInOrder([
                'Olie vervangen',
                'Veluwerit',
                'Banden vervangen',
            ])
            ->assertSeeText('Bekijk trip')
            ->assertDontSeeText('Ritten die je hebt gereden, los van onderhoudsmomenten');
    }

    public function test_timeline_still_scopes_trips_to_the_active_vehicle_owner(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
        ]);

        $otherVehicle = Vehicle::query()->create([
            'user_id' => $otherUser->id,
            'brand' => 'KTM',
            'model' => '790 Adventure',
        ]);

        TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'title' => 'Eigen rit',
            'ridden_at' => '2026-04-02',
            'source_file_path' => 'trip-uploads/own.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        TripLog::query()->create([
            'user_id' => $otherUser->id,
            'vehicle_id' => $otherVehicle->id,
            'title' => 'Verborgen rit',
            'ridden_at' => '2026-04-03',
            'source_file_path' => 'trip-uploads/hidden.gpx',
            'source_format' => 'gpx',
            'status' => TripLog::STATUS_PROCESSED,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertSeeText('Eigen rit')
            ->assertDontSeeText('Verborgen rit');
    }

    public function test_timeline_without_trips_still_renders_maintenance_correctly(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500X',
            'nickname' => 'Forens',
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Kettingset vervangen',
            'km_reading' => 22000,
            'maintenance_date' => '2026-04-02',
            'cost' => 210.00,
        ]);

        $this->actingAs($user)
            ->get('/admin/tijdlijn?vehicle_id=' . $vehicle->id)
            ->assertOk()
            ->assertSeeText('Kettingset vervangen')
            ->assertDontSeeText('Bekijk trip')
            ->assertDontSeeText('Ritten die je hebt gereden, los van onderhoudsmomenten');
    }
}
