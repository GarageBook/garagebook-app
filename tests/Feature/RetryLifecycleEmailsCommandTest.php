<?php

namespace Tests\Feature;

use App\Mail\NoMaintenanceLogDay14Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\LifecycleEmailService;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
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

    public function test_execute_retry_uses_current_laravel_mail_stack(): void
    {
        Config::set('mail.default', 'array');

        /** @var ArrayTransport $transport */
        $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
        $transport->flush();

        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        $originalLog->refresh();

        $this->assertSame(1, $transport->messages()->count());
        $this->assertNotNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $originalLog->retry_status);
    }

    public function test_execute_throttles_retry_sends_to_four_per_second(): void
    {
        Mail::fake();

        $sleepCalls = [];
        $this->app->instance(\App\Support\LifecycleEmailRetryThrottle::class, new \App\Support\LifecycleEmailRetryThrottle(function (int $microseconds) use (&$sleepCalls): void {
            $sleepCalls[] = $microseconds;
        }));

        $firstUser = $this->createLifecycleUser([
            'email' => 'first-throttle@example.com',
        ]);
        $secondUser = $this->createLifecycleUser([
            'email' => 'second-throttle@example.com',
        ]);
        $thirdUser = $this->createLifecycleUser([
            'email' => 'third-throttle@example.com',
        ]);

        $this->createSentLog($firstUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $this->createSentLog($secondUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());
        $this->createSentLog($thirdUser, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, 3);
        $this->assertSame([250000, 250000], $sleepCalls);
    }

    public function test_execute_with_resend_mailer_uses_global_sdk_class_without_redeclare(): void
    {
        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Config::set('mail.default', 'resend');
        Config::set('services.resend.key', 'test-key');

        $realMailManager = Mail::getFacadeRoot();
        Mail::swap(new class
        {
            public function to(string $email): object
            {
                return new class
                {
                    public function send(object $mailable): void
                    {
                        throw new \RuntimeException('Simulated resend transport failure');
                    }
                };
            }
        });

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        Mail::swap($realMailManager);
        $originalLog->refresh();

        $this->assertNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $originalLog->retry_status);
        $this->assertStringContainsString('Simulated resend transport failure', (string) $originalLog->retry_error_message);
    }

    public function test_execute_stops_before_creating_retry_logs_when_mailer_preflight_fails(): void
    {
        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        $service = \Mockery::mock(LifecycleEmailService::class)->makePartial();
        $service->shouldReceive('assertMailDeliveryStackReady')
            ->once()
            ->andThrow(new \RuntimeException('Mailconfig resend is actief maar resend/resend-php ontbreekt. Draai composer install of composer require resend/resend-php.'));
        $this->app->instance(LifecycleEmailService::class, $service);

        $exitCode = Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        $originalLog->refresh();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('resend/resend-php ontbreekt', Artisan::output());
        $this->assertSame(0, LifecycleEmailLog::query()->where('email_key', 'like', 'retry_%')->count());
        $this->assertNull($originalLog->retried_at);
        $this->assertNull($originalLog->retry_status);
        $this->assertNull($originalLog->retry_log_id);
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

    public function test_failed_retries_remain_rerunnable(): void
    {
        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        Config::set('mail.default', 'array');
        $realMailManager = Mail::getFacadeRoot();
        Mail::swap(new class
        {
            public function to(string $email): object
            {
                return new class
                {
                    public function send(object $mailable): void
                    {
                        throw new \RuntimeException('Simulated transport failure');
                    }
                };
            }
        });

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        $originalLog->refresh();

        $this->assertNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $originalLog->retry_status);
        $this->assertNotNull($originalLog->retry_log_id);
        $this->assertStringContainsString('Simulated transport failure', (string) $originalLog->retry_error_message);

        Mail::swap($realMailManager);

        /** @var ArrayTransport $transport */
        $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();
        $transport->flush();

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--execute' => true,
        ]);

        $originalLog->refresh();

        $this->assertNotNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $originalLog->retry_status);
        $this->assertNull($originalLog->retry_error_message);
        $this->assertSame(1, $transport->messages()->count());
        $this->assertSame(2, LifecycleEmailLog::query()->where('email_key', 'like', 'retry_' . LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 . '_%')->count());
    }

    public function test_dry_run_includes_logs_with_failed_retry_status_and_failed_retry_log_id(): void
    {
        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        $failedRetryLog = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => 'retry_' . LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 . '_previous_failed',
            'subject' => 'Vorige retry',
            'status' => LifecycleEmailLog::STATUS_FAILED,
            'failed_at' => now()->subMinutes(20),
            'error_message' => 'Class "Resend" not found',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $originalLog->forceFill([
            'retry_status' => LifecycleEmailLog::STATUS_FAILED,
            'retry_log_id' => $failedRetryLog->id,
            'retry_error_message' => 'Class "Resend" not found',
            'retried_at' => now()->subMinutes(20),
        ])->save();

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('Geselecteerde logs: 1', $output);
        $this->assertStringContainsString('Eligible voor retry nu: 1', $output);
        $this->assertStringContainsString((string) $originalLog->id, $output);
    }

    public function test_dry_run_excludes_logs_with_successful_retry_status(): void
    {
        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        $successfulRetryLog = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => 'retry_' . LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 . '_previous_sent',
            'subject' => 'Vorige succesvolle retry',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $originalLog->forceFill([
            'retry_status' => LifecycleEmailLog::STATUS_SENT,
            'retry_log_id' => $successfulRetryLog->id,
            'retry_error_message' => null,
            'retried_at' => now()->subMinutes(20),
        ])->save();

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('Geselecteerde logs: 0', $output);
        $this->assertStringNotContainsString('| ' . $originalLog->id . '               |', $output);
    }

    public function test_reset_failed_retries_makes_failed_origins_eligible_again(): void
    {
        $user = $this->createLifecycleUser();
        $originalLog = $this->createSentLog($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, now()->subHour());

        $failedRetryLog = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => 'retry_' . LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14 . '_failed_reset',
            'subject' => 'Gefaalde retry',
            'status' => LifecycleEmailLog::STATUS_FAILED,
            'failed_at' => now()->subMinutes(20),
            'error_message' => 'Class "Resend" not found',
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $originalLog->forceFill([
            'retry_status' => LifecycleEmailLog::STATUS_FAILED,
            'retry_log_id' => $failedRetryLog->id,
            'retry_error_message' => 'Class "Resend" not found',
            'retried_at' => now()->subMinutes(20),
        ])->save();

        Artisan::call('garagebook:retry-lifecycle-emails', [
            '--before' => '2026-06-12 11:45:00',
            '--reset-failed-retries' => true,
        ]);

        $originalLog->refresh();
        $output = Artisan::output();

        $this->assertNull($originalLog->retried_at);
        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $originalLog->retry_status);
        $this->assertSame($failedRetryLog->id, $originalLog->retry_log_id);
        $this->assertStringContainsString('Gefaalde retry-markeringen gereset: 1', $output);
        $this->assertStringContainsString('Geselecteerde logs: 1', $output);
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
