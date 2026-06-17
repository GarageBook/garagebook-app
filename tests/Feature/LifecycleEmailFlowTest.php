<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\MaintenanceLogs\Pages\CreateMaintenanceLog;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Jobs\SendLifecycleEmailJob;
use App\Mail\NoMaintenanceLogDay14Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class LifecycleEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LifecycleEmailTemplateSeeder::class);
    }

    public function test_user_without_vehicle_gets_no_vehicle_added_lifecycle_key(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'created_at' => now()->subDays(3),
        ]);

        $this->assertSame(
            LifecycleEmailTemplate::NO_VEHICLE_ADDED,
            app(LifecycleEmailService::class)->resolveEligibleEmailKey($user)
        );

        Artisan::call('garagebook:send-lifecycle-emails');

        Bus::assertDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($user): bool {
            return $job->userId === $user->id
                && $job->emailKey === LifecycleEmailTemplate::NO_VEHICLE_ADDED;
        });
    }

    public function test_user_with_vehicle_but_without_maintenance_gets_no_maintenance_key(): void
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

    public function test_user_with_vehicle_and_maintenance_does_not_get_a_no_maintenance_mail(): void
    {
        $user = User::factory()->create([
            'created_at' => now()->subDays(20),
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tracer 9',
            'current_km' => 12000,
        ]);

        $maintenanceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Oliebeurt',
            'km_reading' => 12000,
            'maintenance_date' => now()->subDays(10)->toDateString(),
        ]);

        $maintenanceLog->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->saveQuietly();

        $this->assertSame(
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            app(LifecycleEmailService::class)->resolveEligibleEmailKey($user)
        );
    }

    public function test_lifecycle_body_uses_first_name_when_available(): void
    {
        $user = User::factory()->create([
            'name' => 'Willem van Veelen',
        ]);

        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->firstOrFail();

        $renderedBody = app(LifecycleEmailService::class)->renderTemplateBody($user, $template);

        $this->assertStringContainsString('Hoi Willem,', $renderedBody);
    }

    public function test_lifecycle_body_falls_back_to_neutral_greeting_without_first_name(): void
    {
        $user = User::factory()->create([
            'name' => '',
        ]);

        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->firstOrFail();

        $renderedBody = app(LifecycleEmailService::class)->renderTemplateBody($user, $template);

        $this->assertStringContainsString('Hoi,', $renderedBody);
        $this->assertStringNotContainsString('Hoi ,', $renderedBody);
    }

    public function test_cta_destination_uses_vehicle_create_history_and_dashboard_routes(): void
    {
        $service = app(LifecycleEmailService::class);

        $userWithVehicle = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $userWithVehicle->id,
            'brand' => 'BMW',
            'model' => 'R 1250 GS',
        ]);

        $userWithoutVehicle = User::factory()->create();

        $this->assertSame(
            VehicleResource::getUrl('create'),
            $service->resolveCtaDestination($userWithoutVehicle, LifecycleEmailTemplate::NO_VEHICLE_ADDED),
        );

        $this->assertSame(
            MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id]),
            $service->resolveCtaDestination($userWithVehicle, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3),
        );

        $this->assertSame(
            MaintenanceLogResource::getUrl('index', ['vehicle_id' => $vehicle->id]),
            $service->resolveCtaDestination($userWithVehicle, LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG),
        );

        $this->assertSame('/admin', $service->resolveCtaDestination($userWithVehicle, LifecycleEmailTemplate::INACTIVE_USER_RETURN));
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

        $this->assertNull($service->reserveLogFor($user, LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG));
    }

    public function test_skipped_lifecycle_log_is_written_when_user_unsubscribes_before_send(): void
    {
        $service = app(LifecycleEmailService::class);
        $tracker = app(AnalyticsEventTracker::class);

        $user = User::factory()->create([
            'created_at' => now()->subDays(14),
            'lifecycle_emails_unsubscribed_at' => now(),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'KTM',
            'model' => '890 Adventure',
            'current_km' => 9000,
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => 'Onderwerp',
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        app(SendLifecycleEmailJob::class, [
            'userId' => $user->id,
            'emailKey' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
        ])->handle($service, $tracker);

        $log->refresh();

        $this->assertSame(LifecycleEmailLog::STATUS_SKIPPED, $log->status);
        $this->assertSame('unsubscribed', $log->reason_skipped);
        $this->assertNotNull($log->skipped_at);
        $this->assertNull($log->sent_at);
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
            'body' => 'Hoi {{ first_name }},\n\nAangepaste body',
            'cta_text' => 'Aangepaste CTA',
        ]);

        $user = User::factory()->create([
            'name' => 'Aprilia Rijder',
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
        ])->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, function (NoMaintenanceLogDay14Mail $mail) use ($user): bool {
            return $mail->template->subject === 'Aangepast onderwerp'
                && $mail->template->cta_text === 'Aangepaste CTA'
                && str_contains($mail->renderedBody, 'Hoi Aprilia,')
                && str_contains($mail->renderedBody, 'Aangepaste body')
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
        $this->assertNotNull($second);
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, LifecycleEmailLog::query()->where('user_id', $user->id)->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->count());
    }

    public function test_re_dispatched_queued_log_sends_only_once(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Queue Rider',
            'created_at' => now()->subDays(14),
        ]);

        Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'BMW',
            'model' => 'F 900 GS',
            'current_km' => 12000,
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => 'Queued',
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        $job = new SendLifecycleEmailJob($user->id, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14, $log->id);

        $job->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));
        $job->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        Mail::assertSent(NoMaintenanceLogDay14Mail::class, 1);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_queued_after_first_maintenance_log_is_re_dispatched_and_processed(): void
    {
        Mail::fake();
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Triumph',
            'model' => 'Tiger 900',
            'current_km' => 8000,
        ]);

        $maintenanceLog = MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Eerste beurt',
            'km_reading' => 8000,
            'maintenance_date' => now()->subDays(10)->toDateString(),
        ]);

        $maintenanceLog->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->saveQuietly();

        $queuedLog = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            'subject' => 'Queued after first maintenance',
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        $this->assertTrue(app(LifecycleEmailService::class)->queueEligibleEmail($user));

        Bus::assertDispatched(SendLifecycleEmailJob::class, function (SendLifecycleEmailJob $job) use ($queuedLog): bool {
            return $job->emailKey === LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG
                && $job->logId === $queuedLog->id;
        });

        (new SendLifecycleEmailJob($user->id, LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG, $queuedLog->id))
            ->handle(app(LifecycleEmailService::class), app(AnalyticsEventTracker::class));

        $queuedLog->refresh();

        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $queuedLog->status);
        $this->assertNotNull($queuedLog->sent_at);
    }

    public function test_goal_completed_at_is_set_when_first_maintenance_log_is_created(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Yamaha',
            'model' => 'Tenere 700',
            'current_km' => 18000,
            'distance_unit' => 'km',
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            'subject' => 'Eerste onderhoud CTA',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now()->subDay(),
        ]);

        $this->actingAs($user);

        Livewire::test(CreateMaintenanceLog::class)
            ->fillForm([
                'vehicle_id' => $vehicle->id,
                'distance_unit' => 'km',
                'description' => 'Olie vervangen',
                'km_reading' => 18100,
                'maintenance_date' => now()->toDateString(),
                'cost' => '89.95',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull($log->fresh()->goal_completed_at);
    }

    public function test_clicked_at_is_set_via_signed_click_tracking_with_log_id(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Transalp',
            'current_km' => 6000,
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Kliktest',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now()->subHour(),
        ]);

        $url = app(LifecycleEmailService::class)->trackedCtaUrl($user, LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3, $log);

        $this->get($url)
            ->assertRedirect(MaintenanceLogResource::getUrl('create', ['vehicle_id' => $vehicle->id]));

        $this->assertNotNull($log->fresh()->clicked_at);
    }

    public function test_unsubscribe_signed_route_marks_user_as_unsubscribed(): void
    {
        $user = User::factory()->create();

        $url = \URL::temporarySignedRoute('lifecycle-emails.unsubscribe', now()->addHour(), ['user' => $user->id]);

        $this->get($url)
            ->assertOk()
            ->assertSeeText('Je bent uitgeschreven');

        $this->assertNotNull($user->fresh()->lifecycle_emails_unsubscribed_at);
    }
}
