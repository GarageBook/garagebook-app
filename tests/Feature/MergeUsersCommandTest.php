<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_command_moves_vehicles_and_airtable_link_to_target_user(): void
    {
        $source = User::factory()->create([
            'name' => 'Willem van Veelen',
            'email' => 'willem@garagebook.nl',
            'airtable_record_id' => 'recGnzeD8Bk4CH5ak',
        ]);

        $target = User::factory()->create([
            'name' => 'Willem',
            'email' => 'willemvanveelen@icloud.com',
            'is_admin' => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $source->id,
            'airtable_record_id' => 'veh123',
            'brand' => 'Aprilia',
            'model' => 'RSV Mille',
            'current_km' => 55000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'airtable_record_id' => 'mnt123',
            'description' => 'Test onderhoud',
            'km_reading' => 55000,
            'maintenance_date' => '2026-04-22',
        ]);

        DB::table('sessions')->insert([
            'id' => 'session-1',
            'user_id' => $source->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => 123456,
        ]);

        $this->artisan('users:merge', [
            '--from' => 'willem@garagebook.nl',
            '--into' => 'willemvanveelen@icloud.com',
            '--force' => true,
            '--delete-source' => true,
        ])->assertSuccessful();

        $target = $target->fresh();
        $vehicle = $vehicle->fresh();

        $this->assertSame('recGnzeD8Bk4CH5ak', $target->airtable_record_id);
        $this->assertTrue($target->is_admin);
        $this->assertSame($target->id, $vehicle->user_id);
        $this->assertDatabaseMissing('users', [
            'id' => $source->id,
        ]);
        $this->assertDatabaseHas('sessions', [
            'id' => 'session-1',
            'user_id' => $target->id,
        ]);
    }
}
