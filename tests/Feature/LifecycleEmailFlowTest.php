<?php

namespace Tests\Feature;

use App\Jobs\SendLifecycleEmailJob;
use App\Mail\NoMaintenanceLogDay14Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\LifecycleEmailService;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LifecycleEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LifecycleEmailTemplateSeeder::class);
    }

    public function test_day_3_mail_is_selected_for_user_with_vehicle_and_without_maintenance(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CB500X',
            'current_km' => 10000,
        ]);

        $this->assertSame(
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            app(LifecycleEmailService::class)->resolveEligibleEmailKey($user)
        );
    }

    public function test_day_14_mail_is_not_queued_twice(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(14),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F 850 GS',
            'current_km' => 5000,
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => 'Bestaand',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        Artisan::call('garagebook:send-lifecycle-emails');

        Bus::assertNotDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->emailKey === LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14;
        });
        $this->assertCount(1, LifecycleEmailLog::query()->where('user_id', $user->id)->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14)->get());
    }

    public function test_day_30_mail_is_not_selected_when_maintenance_logs_exist(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(30),
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'current_km' => 12000,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Oliebeurt',
            'km_reading' => 12000,
            'maintenance_date' => now()->toDateString(),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->assertNull(app(LifecycleEmailService::class)->resolveEligibleEmailKey($user));
    }

    public function test_after_first_maintenance_mail_is_sent_only_once(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Triumph',
            'model' => 'Tiger 900',
            'current_km' => 8000,
        ]);

        $log = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Eerste beurt',
            'km_reading' => 8000,
            'maintenance_date' => now()->subDays(10)->toDateString(),
        ]);

        $log->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->saveQuietly();

        $service = app(LifecycleEmailService::class);

        $this->assertSame(LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG, $service->resolveEligibleEmailKey($user));

        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            'subject' => 'Verzonden',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->assertNull($service->resolveEligibleEmailKey($user));
    }

    public function test_inactive_template_is_not_sent(): void
    {
        Bus::fake();

        LifecycleEmailTemplate::query()
            ->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)
            ->update(['is_active' => false]);

        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Suzuki',
            'model' => 'V-Strom',
            'current_km' => 14000,
        ]);

        Artisan::call('garagebook:send-lifecycle-emails');

        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    public function test_unsubscribed_users_do_not_receive_lifecycle_emails(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(14),
            'lifecycle_emails_unsubscribed_at' => now()->subDay(),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'KTM',
            'model' => '890 Adventure',
            'current_km' => 9000,
        ]);

        Artisan::call('garagebook:send-lifecycle-emails');

        Bus::assertNotDispatched(SendLifecycleEmailJob::class);
    }

    public function test_command_dispatches_queue_jobs_for_eligible_users(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(14),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Moto Guzzi',
            'model' => 'V85 TT',
            'current_km' => 6000,
        ]);

        Artisan::call('garagebook:send-lifecycle-emails');

        Bus::assertDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->emailKey === LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14;
        });
    }

    public function test_job_uses_current_template_content_and_writes_lifecycle_log(): void
    {
        Mail::fake();

        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14)->firstOrFail();
        $template->update([
            'subject' => 'Aangepast onderwerp',
            'body' => 'Aangepaste body',
            'cta_text' => 'Aangepaste CTA',
        ]);

        $user = User::factory()->create([
            'created_at' => now()->subDays(14),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Aprilia',
            'model' => 'Tuareg',
            'current_km' => 4000,
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => $template->subject,
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        app(SendLifecycleEmailJob::class, [
            'userId' => $user->id,
            'emailKey' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
        ])->handle(app(LifecycleEmailService::class), app(\App\Support\AnalyticsEventTracker::class));

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, function (NoMaintenanceLogDay14Mail $mail) use ($template, $user): bool {
            return $mail->template->subject === 'Aangepast onderwerp'
                && $mail->template->body === 'Aangepaste body'
                && $mail->template->cta_text === 'Aangepaste CTA'
                && $mail->user->is($user);
        });

        $log = LifecycleEmailLog::query()->where('user_id', $user->id)->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14)->firstOrFail();

        $this->assertSame('Aangepast onderwerp', $log->subject);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
    }


    public function test_lifecycle_email_logs_prevent_duplicate_reservations(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Ducati',
            'model' => 'DesertX',
            'current_km' => 3500,
        ]);

        $service = app(LifecycleEmailService::class);

        $first = $service->reserveLogFor($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3);
        $second = $service->reserveLogFor($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, LifecycleEmailLog::query()->where('user_id', $user->id)->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->count());
    }

    public function test_unsubscribe_signed_route_marks_user_as_unsubscribed(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('lifecycle-emails.unsubscribe', now()->addHour(), ['user' => $user->id]);

        $this->get($url)
            ->assertOk()
            ->assertSeeText('Je bent uitgeschreven');

        $this->assertNotNull($user->fresh()->lifecycle_emails_unsubscribed_at);
    }
}
