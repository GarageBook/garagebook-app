<?php

namespace Tests\Feature;

use App\Jobs\SendLifecycleEmailJob;
use App\Mail\Lifecycle\NoVehicleDay2Mail;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Lifecycle\LifecycleEmailService as NoVehicleLifecycleEmailService;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LifecycleNoVehicleDay2Test extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_vehicle_is_queued(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
            'email_verified_at' => now()->subDays(3),
        ]);

        $result = app(NoVehicleLifecycleEmailService::class)->queueNoVehicleUsers();

        $this->assertSame(['found' => 1, 'queued' => 1, 'skipped' => 0], $result);
        $this->assertDatabaseHas('lifecycle_email_logs', [
            'user_id' => $user->id,
            'email_key' => LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
            'trigger' => LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
            'mail_class' => NoVehicleDay2Mail::class,
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        $log = LifecycleEmailLog::query()->firstOrFail();
        $this->assertNotNull($log->queued_at);

        Bus::assertDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($user, $log): bool {
            return $job->userId === $user->id
                && $job->emailKey === LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2
                && $job->logId === $log->id;
        });
    }

    public function test_user_with_vehicle_is_not_queued(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
            'email_verified_at' => now()->subDays(3),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
        ]);

        $result = app(NoVehicleLifecycleEmailService::class)->queueNoVehicleUsers();

        $this->assertSame(['found' => 0, 'queued' => 0, 'skipped' => 0], $result);
        $this->assertDatabaseCount('lifecycle_email_logs', 0);
        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    public function test_user_gets_no_vehicle_day2_trigger_only_once(): void
    {
        Bus::fake();

        User::factory()->create([
            'created_at' => now()->subDays(3),
            'email_verified_at' => now()->subDays(3),
        ]);

        app(NoVehicleLifecycleEmailService::class)->queueNoVehicleUsers();
        app(NoVehicleLifecycleEmailService::class)->queueNoVehicleUsers();

        $this->assertSame(1, LifecycleEmailLog::query()
            ->where('trigger', LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2)
            ->count());
        Bus::assertDispatched(SendLifecycleEmailJob::class, 1);
    }

    public function test_job_skips_send_when_vehicle_was_added_after_queueing(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
            'email_verified_at' => now()->subDays(3),
        ]);
        $log = $this->queuedLogFor($user);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Toyota',
            'model' => 'Highlander',
        ]);

        (new SendLifecycleEmailJob($user->id, LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2, $log->id))
            ->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertNothingSent();
        $log->refresh();

        $this->assertSame(LifecycleEmailLog::STATUS_SKIPPED, $log->status);
        $this->assertSame('vehicle_added', $log->error);
        $this->assertSame('vehicle_added', $log->reason_skipped);
        $this->assertNotNull($log->skipped_at);
    }

    public function test_job_sends_mail_and_marks_log_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
            'email_verified_at' => now()->subDays(3),
        ]);
        $log = $this->queuedLogFor($user);

        (new SendLifecycleEmailJob($user->id, LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2, $log->id))
            ->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertSent(NoVehicleDay2Mail::class, function (NoVehicleDay2Mail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->ctaUrl === url('/admin/vehicles/create');
        });

        $log->refresh();

        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->error);
    }

    public function test_lifecycle_job_rate_limiter_releases_second_job_without_running_handler(): void
    {
        RateLimiter::clear(md5('lifecycle-email'.'resend-lifecycle-email'));

        $middleware = (new RateLimited('lifecycle-email'))->releaseAfter(1);
        $job = new class
        {
            public array $released = [];

            public function release(int $delay): void
            {
                $this->released[] = $delay;
            }
        };
        $handled = 0;

        for ($i = 0; $i < 2; $i++) {
            $middleware->handle($job, function () use (&$handled): void {
                $handled++;
            });
        }

        $this->assertSame(1, $handled);
        $this->assertSame([1], $job->released);
    }

    private function queuedLogFor(User $user): LifecycleEmailLog
    {
        return LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
            'trigger' => LifecycleEmailLog::TRIGGER_NO_VEHICLE_DAY2,
            'subject' => 'Je GarageBook is nog een beetje leeg... 😉',
            'mail_class' => NoVehicleDay2Mail::class,
            'status' => LifecycleEmailLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
    }
}
