<?php

namespace Tests\Feature\Lifecycle;

use App\Mail\Lifecycle\LifecycleEmailMailable;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\LifecycleRuleEvaluation;
use App\Models\User;
use App\Services\Lifecycle\Mail\LifecycleMailAdapter;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleMailAdapterPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_result_maps_to_existing_template(): void
    {
        $this->seed(LifecycleEmailTemplateSeeder::class);
        $user = User::factory()->create();

        $preview = app(LifecycleMailAdapter::class)->preview($user, LifecycleRuleResult::match(
            'no_vehicle',
            'Geen voertuig gevonden.',
            100,
            2,
        ));

        $this->assertSame(LifecycleEmailTemplate::NO_VEHICLE_DAY2, $preview['template_key']);
        $this->assertSame('Voeg je eerste voertuig toe', $preview['subject']);
        $this->assertSame('Voertuig toevoegen', $preview['cta']);
        $this->assertTrue($preview['eligible']);
        $this->assertFalse($preview['blocked_by_mail_cap']);
        $this->assertFalse($preview['blocked_by_unsubscribe']);
    }

    public function test_first_maintenance_and_inactive_rules_map_to_existing_templates(): void
    {
        $this->seed(LifecycleEmailTemplateSeeder::class);
        $user = User::factory()->create();
        $adapter = app(LifecycleMailAdapter::class);

        $firstMaintenance = $adapter->preview($user, LifecycleRuleResult::match(
            'first_maintenance',
            'Voertuig zonder onderhoud.',
            90,
            3,
        ));
        $inactive = $adapter->preview($user, LifecycleRuleResult::match(
            'inactive_maintenance',
            'Inactieve gebruiker met onderhoud.',
            50,
            30,
        ));

        $this->assertSame(LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3, $firstMaintenance['template_key']);
        $this->assertSame(LifecycleEmailTemplate::INACTIVE_USER_RETURN, $inactive['template_key']);
    }

    public function test_unsubscribed_user_is_blocked(): void
    {
        $this->seed(LifecycleEmailTemplateSeeder::class);
        $user = User::factory()->create([
            'lifecycle_emails_unsubscribed_at' => now(),
        ]);

        $preview = app(LifecycleMailAdapter::class)->preview($user, LifecycleRuleResult::match(
            'no_vehicle',
            'Geen voertuig gevonden.',
            100,
            2,
        ));

        $this->assertFalse($preview['eligible']);
        $this->assertTrue($preview['blocked_by_unsubscribe']);
        $this->assertStringContainsString('uitgeschreven', $preview['reason']);
    }

    public function test_seven_day_mail_cap_blocks_queued_processing_or_sent_logs(): void
    {
        $this->seed(LifecycleEmailTemplateSeeder::class);
        $user = User::factory()->create();
        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Recent lifecycle bericht',
            'status' => LifecycleEmailLog::STATUS_QUEUED,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $preview = app(LifecycleMailAdapter::class)->preview($user, LifecycleRuleResult::match(
            'no_vehicle',
            'Geen voertuig gevonden.',
            100,
            2,
        ));

        $this->assertFalse($preview['eligible']);
        $this->assertTrue($preview['blocked_by_mail_cap']);
        $this->assertStringContainsString('laatste 7 dagen', $preview['reason']);
    }

    public function test_upload_document_and_vehicle_photo_rules_map_to_active_templates(): void
    {
        $this->seed(LifecycleEmailTemplateSeeder::class);
        $user = User::factory()->create();
        $adapter = app(LifecycleMailAdapter::class);

        $uploadDocument = $adapter->preview($user, LifecycleRuleResult::match(
            'upload_document',
            'Eerste document ontbreekt.',
            70,
            7,
        ));
        $vehiclePhoto = $adapter->preview($user, LifecycleRuleResult::match(
            'vehicle_photo_reminder',
            'Voertuigfoto ontbreekt.',
            60,
            10,
        ));

        $this->assertSame(LifecycleEmailTemplate::UPLOAD_DOCUMENT, $uploadDocument['template_key']);
        $this->assertSame('Voeg bewijs toe aan je onderhoud', $uploadDocument['subject']);
        $this->assertSame('Document toevoegen', $uploadDocument['cta']);
        $this->assertTrue($uploadDocument['eligible']);

        $this->assertSame(LifecycleEmailTemplate::VEHICLE_PHOTO_REMINDER, $vehiclePhoto['template_key']);
        $this->assertSame('Maak je GarageBook herkenbaarder', $vehiclePhoto['subject']);
        $this->assertSame('Foto toevoegen', $vehiclePhoto['cta']);
        $this->assertTrue($vehiclePhoto['eligible']);
    }

    public function test_command_mail_preview_queues_sends_and_logs_nothing(): void
    {
        Mail::fake();
        Queue::fake();
        $this->seed(LifecycleEmailTemplateSeeder::class);
        User::factory()->create();

        $this->artisan('garagebook:lifecycle:evaluate-rules', [
            '--preview-mail' => true,
            '--no-store' => true,
        ])
            ->expectsOutputToContain('Mail preview user=')
            ->assertSuccessful();

        Mail::assertNothingSent();
        Mail::assertNotSent(LifecycleEmailMailable::class);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('lifecycle_email_logs', 0);
        $this->assertDatabaseCount('lifecycle_rule_evaluations', 0);
    }

    public function test_rule_evaluations_are_historical_shadow_records_without_retention_yet(): void
    {
        $user = User::factory()->create();

        $this->artisan('garagebook:lifecycle:evaluate-rules')->assertSuccessful();

        $this->assertSame(5, LifecycleRuleEvaluation::query()->where('user_id', $user->id)->count());
    }
}
