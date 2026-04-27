<?php

namespace Tests\Feature;

use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OptimizeMaintenanceLogMediaJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_queued_for_existing_maintenance_log(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tenere 700',
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test',
            'km_reading' => 1200,
            'maintenance_date' => now()->toDateString(),
        ]);

        OptimizeMaintenanceLogMedia::dispatch($log->id);

        Queue::assertPushed(OptimizeMaintenanceLogMedia::class, function (OptimizeMaintenanceLogMedia $job) use ($log): bool {
            return $job->maintenanceLogId === $log->id;
        });
    }
}
