<?php

namespace Tests\Feature;

use App\Jobs\SendLifecycleEmailJob;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LifecycleEmailRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LifecycleEmailTemplateSeeder::class);
    }

    public function test_large_rate_limited_batch_is_released_without_attempt_exhaustion_policy(): void
    {
        RateLimiter::clear(md5('lifecycle-email'.'resend-lifecycle-email'));

        $handled = 0;
        $released = 0;

        for ($i = 0; $i < 200; $i++) {
            $queueJob = new class($released)
            {
                public function __construct(public int &$released) {}

                public function release(int $delay): void
                {
                    $this->released++;
                }
            };

            (new RateLimited('lifecycle-email'))->releaseAfter(1)->handle(
                $queueJob,
                function () use (&$handled): void {
                    $handled++;
                },
            );
        }

        $job = new SendLifecycleEmailJob(1, LifecycleEmailTemplate::NO_VEHICLE_DAY2);

        $this->assertSame(1, $handled);
        $this->assertSame(199, $released);
        $this->assertSame(0, $job->tries);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertTrue($job->retryUntil() > now()->addMinutes(29));

    }

    public function test_exception_after_processing_can_be_retried_by_same_job(): void
    {
        Mail::fake();

        [$user, $log] = $this->eligibleNoVehicleLog();
        $job = new SendLifecycleEmailJob($user->id, LifecycleEmailTemplate::NO_VEHICLE_DAY2, $log->id);
        $failingService = Mockery::mock(app(LifecycleEmailService::class))->makePartial();
        $failingService->shouldReceive('assertMailDeliveryStackReady')
            ->once()
            ->andThrow(new RuntimeException('temporary transport failure'));

        try {
            $job->handle($failingService, app(AnalyticsEventTracker::class));
            $this->fail('Expected transport exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('temporary transport failure', $exception->getMessage());
        }

        $this->assertSame(LifecycleEmailLog::STATUS_PROCESSING, $log->fresh()->status);
        $this->assertSame($job->processingToken, $log->fresh()->processing_token);

        $job->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertSentCount(1);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_definitive_failure_marks_owned_processing_log_failed(): void
    {
        [$user, $log] = $this->eligibleNoVehicleLog();
        $job = new SendLifecycleEmailJob($user->id, LifecycleEmailTemplate::NO_VEHICLE_DAY2, $log->id);

        $log->forceFill([
            'status' => LifecycleEmailLog::STATUS_PROCESSING,
            'processing_token' => $job->processingToken,
        ])->save();

        $job->failed(new RuntimeException('final failure'));

        $log->refresh();

        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $log->status);
        $this->assertSame('final failure', $log->error_message);
        $this->assertNotNull($log->failed_at);
    }

    public function test_duplicate_job_cannot_send_log_owned_by_another_job(): void
    {
        Mail::fake();

        [$user, $log] = $this->eligibleNoVehicleLog();
        $owner = new SendLifecycleEmailJob($user->id, LifecycleEmailTemplate::NO_VEHICLE_DAY2, $log->id);
        $duplicate = new SendLifecycleEmailJob($user->id, LifecycleEmailTemplate::NO_VEHICLE_DAY2, $log->id);

        $log->forceFill([
            'status' => LifecycleEmailLog::STATUS_PROCESSING,
            'processing_token' => $owner->processingToken,
        ])->save();

        $duplicate->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));
        $owner->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertSentCount(1);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_stale_processing_recovery_is_dry_run_by_default(): void
    {
        Bus::fake();

        [, $log] = $this->eligibleNoVehicleLog(LifecycleEmailLog::STATUS_PROCESSING);
        $this->makeOld($log);

        $exit = Artisan::call('garagebook:recover-stale-lifecycle-emails', [
            '--before' => now()->subDay()->toDateTimeString(),
            '--ids' => (string) $log->id,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Ambiguous: 1', Artisan::output());
        $this->assertSame(LifecycleEmailLog::STATUS_PROCESSING, $log->fresh()->status);
        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    public function test_failed_recovery_is_dry_run_by_default(): void
    {
        Bus::fake();

        [, $log] = $this->eligibleNoVehicleLog(LifecycleEmailLog::STATUS_FAILED);
        $log->forceFill(['failed_at' => now()->subDay()])->save();

        $exit = Artisan::call('garagebook:recover-failed-lifecycle-emails', [
            '--from' => now()->subDays(2)->toDateTimeString(),
            '--to' => now()->toDateTimeString(),
            '--email-key' => [LifecycleEmailTemplate::NO_VEHICLE_DAY2],
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Recoverable: 1', Artisan::output());
        $this->assertDatabaseCount('lifecycle_email_logs', 1);
        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    public function test_failed_recovery_does_not_offer_mail_when_user_is_no_longer_eligible(): void
    {
        Bus::fake();

        [$user, $log] = $this->eligibleNoVehicleLog(LifecycleEmailLog::STATUS_FAILED);
        $log->forceFill(['failed_at' => now()->subDay()])->save();

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500X',
        ]);

        Artisan::call('garagebook:recover-failed-lifecycle-emails', [
            '--from' => now()->subDays(2)->toDateTimeString(),
            '--to' => now()->toDateTimeString(),
            '--ids' => (string) $log->id,
            '--execute' => true,
            '--confirm' => true,
        ]);

        $this->assertStringContainsString('No longer eligible: 1', Artisan::output());
        $this->assertDatabaseCount('lifecycle_email_logs', 1);
        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    public function test_eligible_failed_recovery_creates_auditable_retry_and_queues_it(): void
    {
        Bus::fake();

        [$user, $log] = $this->eligibleNoVehicleLog(LifecycleEmailLog::STATUS_FAILED);
        $log->forceFill(['failed_at' => now()->subDay()])->save();

        Artisan::call('garagebook:recover-failed-lifecycle-emails', [
            '--from' => now()->subDays(2)->toDateTimeString(),
            '--to' => now()->toDateTimeString(),
            '--ids' => (string) $log->id,
            '--execute' => true,
            '--confirm' => true,
        ]);

        $retryLog = LifecycleEmailLog::query()->where('retry_of_log_id', $log->id)->firstOrFail();

        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $log->fresh()->status);
        $this->assertSame(LifecycleEmailLog::STATUS_QUEUED, $retryLog->status);
        Bus::assertDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($user, $retryLog): bool {
            return $job->userId === $user->id
                && $job->emailKey === LifecycleEmailTemplate::NO_VEHICLE_DAY2
                && $job->logId === $retryLog->id;
        });
    }

    public function test_stale_queued_recovery_can_be_explicitly_requeued(): void
    {
        Bus::fake();

        [$user, $log] = $this->eligibleNoVehicleLog();
        $this->makeOld($log);

        Artisan::call('garagebook:recover-stale-lifecycle-emails', [
            '--before' => now()->subDay()->toDateTimeString(),
            '--ids' => (string) $log->id,
            '--execute' => true,
            '--confirm' => true,
        ]);

        $this->assertSame(LifecycleEmailLog::STATUS_QUEUED, $log->fresh()->status);
        $this->assertNull($log->fresh()->processing_token);
        Bus::assertDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($user, $log): bool {
            return $job->userId === $user->id && $job->logId === $log->id;
        });
    }

    public function test_recovery_retry_log_can_be_sent_without_changing_original_failure(): void
    {
        Mail::fake();

        [$user, $original] = $this->eligibleNoVehicleLog(LifecycleEmailLog::STATUS_FAILED);
        $original->forceFill(['failed_at' => now()->subDay()])->save();

        $result = app(LifecycleEmailService::class)->recoverFailedLog($original);
        $retryLog = LifecycleEmailLog::query()->findOrFail($result['retry_log_id']);
        $this->assertSame($original->id, $retryLog->retry_of_log_id);

        (new SendLifecycleEmailJob(
            $user->id,
            LifecycleEmailTemplate::NO_VEHICLE_DAY2,
            $retryLog->id,
        ))->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertSentCount(1);
        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $original->fresh()->status);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $retryLog->fresh()->status);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $original->fresh()->retry_status);
        $this->assertTrue($original->fresh()->isResolvedFailure());
    }

    public function test_processing_recovery_requires_explicit_ambiguous_confirmation(): void
    {
        Bus::fake();

        [, $log] = $this->eligibleNoVehicleLog(LifecycleEmailLog::STATUS_PROCESSING);
        $this->makeOld($log);

        Artisan::call('garagebook:recover-stale-lifecycle-emails', [
            '--before' => now()->subDay()->toDateTimeString(),
            '--ids' => (string) $log->id,
            '--execute' => true,
            '--confirm' => true,
        ]);

        $this->assertSame(LifecycleEmailLog::STATUS_PROCESSING, $log->fresh()->status);
        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    /**
     * @return array{User, LifecycleEmailLog}
     */
    private function eligibleNoVehicleLog(string $status = LifecycleEmailLog::STATUS_QUEUED): array
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
            'email_verified_at' => now()->subDays(3),
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_VEHICLE_DAY2,
            'subject' => 'Voeg je motor toe',
            'status' => $status,
            'queued_at' => now()->subDays(2),
        ]);

        return [$user, $log];
    }

    private function makeOld(LifecycleEmailLog $log): void
    {
        $log->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();
    }
}
