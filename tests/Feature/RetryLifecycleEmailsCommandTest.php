<?php

namespace Tests\Feature;

use App\Mail\NoMaintenanceLogDay14Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RetryLifecycleEmailsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-12 12:00:00');
        $this->seed(LifecycleEmailTemplateSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dry_run_sends_nothing_and_reports_counts(): void
    {
        Mail::fake();

        $eligibleUser = $this->createLifecycleUser();
        $unsubscribedUser = $this->createLifecycleUser([
            'email' => 'unsubscribed@example.com',
            'lifecycle_emails_unsubscribed_at' => now()->subDay(),
        ]);
        $ineligibleUser = $this->createLifecycleUser([
            'email' => 'ineligible@example.com',
        ]);

        $this->createSentLog($eligibleUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $this->createSentLog($unsubscribedUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $this->createSentLog($ineligibleUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        $vehicle = $ineligibleUser->vehicles()->firstOrFail();

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Later onderhoud toegevoegd',
            'km_reading' => 1500,
            'maintenance_date' => now()->toDateString(),
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
        ]);

        Mail::assertNothingSent();

        $output = Artisan::output();

        $this->assertStringContainsString('Mode: dry-run', $output);
        $this->assertStringContainsString('Geselecteerde logs: 3', $output);
        $this->assertStringContainsString('no_maintenance_log_day_14: 3', $output);
        $this->assertStringContainsString('Eligible voor retry nu: 1', $output);
        $this->assertStringContainsString('Overgeslagen door unsubscribe: 1', $output);
        $this->assertStringContainsString('Overgeslagen door ineligibility: 1', $output);
    }

    public function test_execute_sends_only_selected_logs(): void
    {
        Mail::fake();

        $eligibleUser = $this->createLifecycleUser();
        $recentUser = $this->createLifecycleUser(['email' => 'recent@example.com']);
        $failedUser = $this->createLifecycleUser(['email' => 'failed@example.com']);

        $originalLog = $this->createSentLog($eligibleUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $this->createSentLog($recentUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subMinutes(5));

        LifecycleEmailLog::query()->create([
            'user_id' => $failedUser->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => 'Niet selecteren',
            'status' => LifecycleEmailLog::STATUS_FAILED,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, 1);

        $originalLog->refresh();
        $this->assertNotNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $originalLog->retry_status);
        $this->assertNotNull($originalLog->retry_log_id);

        $retryLog = LifecycleEmailLog::query()->findOrFail($originalLog->retry_log_id);
        $this->assertStringStartsWith('retry_' . LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 . '_', $retryLog->email_key);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $retryLog->status);

        $this->assertSame(1, LifecycleEmailLog::query()->where('email_key', 'like', 'retry_%')->count());
    }

    public function test_test_logs_are_skipped(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser();

        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => 'test_' . LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 . '_20260612110000',
            'subject' => 'Test log',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::assertNothingSent();
        $this->assertSame(0, LifecycleEmailLog::query()->where('email_key', 'like', 'retry_%')->count());
        $this->assertStringContainsString('Geselecteerde logs: 0', Artisan::output());
    }

    public function test_unsubscribed_users_are_skipped_on_execute(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser([
            'lifecycle_emails_unsubscribed_at' => now()->subDay(),
        ]);
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::assertNothingSent();
        $originalLog->refresh();
        $this->assertNull($originalLog->retried_at);
        $this->assertNull($originalLog->retry_log_id);
    }

    public function test_users_that_are_no_longer_eligible_are_skipped(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $vehicle = $user->vehicles()->firstOrFail();

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud toegevoegd',
            'km_reading' => 1234,
            'maintenance_date' => now()->toDateString(),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::assertNothingSent();
        $originalLog->refresh();
        $this->assertNull($originalLog->retried_at);
        $this->assertNull($originalLog->retry_log_id);
    }

    public function test_duplicate_retry_is_prevented(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, 1);
        $originalLog->refresh();
        $this->assertSame(1, LifecycleEmailLog::query()->where('email_key', 'like', 'retry_%')->count());
        $this->assertNotNull($originalLog->retried_at);
    }

    public function test_retry_log_and_original_log_metadata_are_written(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        $originalLog->refresh();
        $retryLog = LifecycleEmailLog::query()->findOrFail($originalLog->retry_log_id);

        $this->assertNotNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $originalLog->retry_status);
        $this->assertNull($originalLog->retry_error_message);
        $this->assertSame($retryLog->id, $originalLog->retry_log_id);
        $this->assertSame($user->id, $retryLog->user_id);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $retryLog->status);
    }

    public function test_ignore_eligibility_allows_retry_for_now_ineligible_user(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $vehicle = $user->vehicles()->firstOrFail();

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Onderhoud toegevoegd',
            'km_reading' => 1234,
            'maintenance_date' => now()->toDateString(),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
            '--ignore-eligibility' => true,
        ]);

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, 1);
        $originalLog->refresh();
        $this->assertNotNull($originalLog->retried_at);
    }

    public function test_after_first_maintenance_log_can_be_retried_when_user_still_matches_that_state(): void
    {
        Mail::fake();

        $user = $this->createLifecycleUser(['created_at' => now()->subDays(40)]);
        $vehicle = $user->vehicles()->firstOrFail();

        $maintenanceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Eerste onderhoud',
            'km_reading' => 2400,
            'maintenance_date' => now()->subDays(8)->toDateString(),
        ]);

        $maintenanceLog->forceFill([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ])->saveQuietly();

        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG, now()->subHour());

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        $originalLog->refresh();
        $this->assertNotNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $originalLog->retry_status);
        $this->assertSame(1, LifecycleEmailLog::query()->where('email_key', 'like', 'retry_after_first_maintenance_log_%')->count());
    }

    private function createLifecycleUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'name' => 'Lifecycle User',
            'email' => 'lifecycle-' . str()->uuid() . '@example.com',
            'created_at' => now()->subDays(20),
        ], $attributes));

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Africa Twin',
            'current_km' => 1000,
        ]);

        return $user;
    }

    private function createSentLog(User $user, string $emailKey, Carbon $sentAt): LifecycleEmailLog
    {
        return LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => $emailKey,
            'subject' => 'Lifecycle subject',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => $sentAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }
}
