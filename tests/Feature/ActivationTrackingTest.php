<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivationTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_registration_gets_provenance_and_onboarding_version_without_backfill(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00 UTC');

        $user = User::factory()->create();

        $this->assertSame('native', $user->record_origin);
        $this->assertSame(User::CURRENT_ONBOARDING_VERSION, $user->onboarding_version);
        $this->assertTrue($user->source_created_at->equalTo(now()));
    }

    public function test_imported_records_do_not_fabricate_onboarding_reminder_or_publication_times(): void
    {
        $user = User::factory()->create([
            'airtable_record_id' => 'rec-user',
            'record_origin' => 'airtable_import',
            'source_created_at' => null,
        ]);
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'airtable_record_id' => 'rec-vehicle',
            'record_origin' => 'airtable_import',
            'brand' => 'Import',
            'model' => 'Vehicle',
            'is_public' => true,
        ]);
        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'airtable_record_id' => 'rec-log',
            'record_origin' => 'airtable_import',
            'description' => 'Historisch',
            'maintenance_date' => today(),
            'km_reading' => 100,
            'reminder_enabled' => true,
        ]);

        $this->assertNull($user->onboarding_version);
        $this->assertNull($user->source_created_at);
        $this->assertNull($vehicle->first_published_at);
        $this->assertNull($log->reminder_first_enabled_at);
    }

    public function test_legacy_unknown_records_remain_unknown_when_reenabled(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Legacy',
            'model' => 'Vehicle',
            'is_public' => false,
        ]);
        $vehicle->forceFill(['record_origin' => null, 'first_published_at' => null])->saveQuietly();
        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Legacy onderhoud',
            'maintenance_date' => today(),
            'km_reading' => 100,
            'reminder_enabled' => false,
        ]);
        $log->forceFill(['record_origin' => null, 'reminder_first_enabled_at' => null])->saveQuietly();

        $vehicle->update(['is_public' => true]);
        $log->update(['reminder_enabled' => true]);

        $this->assertNull($vehicle->fresh()->first_published_at);
        $this->assertNull($log->fresh()->reminder_first_enabled_at);
    }

    public function test_reminder_first_enabled_at_is_write_once(): void
    {
        $log = $this->maintenanceLog(false);
        Carbon::setTestNow('2026-09-28 10:00:00 UTC');
        $log->update(['reminder_enabled' => true]);
        $first = $log->fresh()->reminder_first_enabled_at;

        Carbon::setTestNow('2026-09-29 10:00:00 UTC');
        $log->update(['reminder_enabled' => false]);
        $log->update(['reminder_enabled' => true]);

        $this->assertTrue($first->equalTo($log->fresh()->reminder_first_enabled_at));
    }

    public function test_first_published_at_is_write_once(): void
    {
        Carbon::setTestNow('2026-09-28 10:00:00 UTC');
        $vehicle = Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => 'Test',
            'model' => 'Private',
            'is_public' => false,
        ]);
        $this->assertNull($vehicle->first_published_at);

        Carbon::setTestNow('2026-09-29 10:00:00 UTC');
        $vehicle->update(['is_public' => true]);
        $first = $vehicle->fresh()->first_published_at;
        Carbon::setTestNow('2026-09-30 10:00:00 UTC');
        $vehicle->update(['is_public' => false]);
        $vehicle->update(['is_public' => true]);

        $this->assertTrue($first->equalTo($vehicle->fresh()->first_published_at));
    }

    public function test_authenticated_activity_is_deduplicated_per_amsterdam_calendar_day(): void
    {
        $user = User::factory()->create();
        Carbon::setTestNow('2026-03-29 21:30:00 UTC');
        $this->actingAs($user)->get('/');
        Carbon::setTestNow('2026-03-29 21:45:00 UTC');
        $this->get('/');
        Carbon::setTestNow('2026-03-29 22:15:00 UTC'); // March 30 in Amsterdam
        $this->get('/');

        $this->assertDatabaseCount('user_daily_activities', 2);
        $this->assertDatabaseHas('user_daily_activities', ['user_id' => $user->id, 'activity_date' => '2026-03-29']);
        $this->assertDatabaseHas('user_daily_activities', ['user_id' => $user->id, 'activity_date' => '2026-03-30']);
    }

    public function test_missing_activity_table_never_blocks_the_product_request(): void
    {
        $user = User::factory()->create();
        Schema::drop('user_daily_activities');

        $this->actingAs($user)->get('/')->assertRedirect();
    }

    public function test_activity_write_failure_is_logged_and_never_blocks_the_product_request(): void
    {
        $user = User::factory()->create();
        Log::spy();
        DB::statement("CREATE TRIGGER reject_activity BEFORE INSERT ON user_daily_activities BEGIN SELECT RAISE(FAIL, 'test failure'); END");

        $this->actingAs($user)->get('/')->assertRedirect();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Daily user activity could not be recorded.'
                && $context['user_id'] === $user->id
                && isset($context['exception'])
        );
    }

    private function maintenanceLog(bool $reminder): MaintenanceLog
    {
        $vehicle = Vehicle::query()->create([
            'user_id' => User::factory()->create()->id,
            'brand' => 'Test',
            'model' => 'Vehicle',
        ]);

        return MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud',
            'maintenance_date' => today(),
            'km_reading' => 100,
            'reminder_enabled' => $reminder,
        ]);
    }
}
