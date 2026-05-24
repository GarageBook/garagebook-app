<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class VehiclePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_update_and_delete_own_vehicle(): void
    {
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'current_km' => 12000,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $vehicle));
        $this->assertTrue(Gate::forUser($user)->allows('update', $vehicle));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $vehicle));
    }

    public function test_user_cannot_view_update_or_delete_another_users_vehicle(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'current_km' => 8000,
        ]);

        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $vehicle));
        $this->assertFalse(Gate::forUser($otherUser)->allows('update', $vehicle));
        $this->assertFalse(Gate::forUser($otherUser)->allows('delete', $vehicle));
    }

    public function test_admin_bypass_applies_to_vehicle_policy(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $owner = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $owner->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
            'current_km' => 40000,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $vehicle));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $vehicle));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $vehicle));
    }
}
