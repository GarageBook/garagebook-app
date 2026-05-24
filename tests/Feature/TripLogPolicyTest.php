<?php

namespace Tests\Feature;

use App\Models\TripLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TripLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_update_and_delete_own_trip_log(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F 900 GS',
        ]);

        $tripLog = TripLog::query()->create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'ridden_at' => '2026-05-20',
            'source_file_path' => 'trip-uploads/own-trip.gpx',
            'source_format' => 'gpx',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $tripLog));
        $this->assertTrue(Gate::forUser($user)->allows('update', $tripLog));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $tripLog));
    }

    public function test_user_cannot_view_update_or_delete_another_users_trip_log(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Triumph',
            'model' => 'Tiger 900',
        ]);

        $tripLog = TripLog::query()->create([
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'ridden_at' => '2026-05-20',
            'source_file_path' => 'trip-uploads/foreign-trip.gpx',
            'source_format' => 'gpx',
        ]);

        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $tripLog));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $tripLog));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $tripLog));
    }

    public function test_admin_bypass_applies_to_trip_log_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'KTM',
            'model' => '890 Adventure',
        ]);

        $tripLog = TripLog::query()->create([
            'user_id' => $owner->id,
            'vehicle_id' => $vehicle->id,
            'ridden_at' => '2026-05-20',
            'source_file_path' => 'trip-uploads/admin-trip.gpx',
            'source_format' => 'gpx',
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $tripLog));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $tripLog));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $tripLog));
    }
}
