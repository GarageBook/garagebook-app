<?php

namespace Tests\Feature;

use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use App\Filament\Resources\LifecycleEmailLogs\Pages\ListLifecycleEmailLogs;
use App\Filament\Resources\LifecycleEmailTemplates\LifecycleEmailTemplateResource;
use App\Filament\Resources\LifecycleEmailTemplates\Pages\EditLifecycleEmailTemplate;
use App\Mail\NoMaintenanceLogDay3Mail;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Services\LifecycleEmailLogExportService;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class LifecycleEmailAdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LifecycleEmailTemplateSeeder::class);
    }

    public function test_admin_can_update_lifecycle_email_template_content(): void
    {
        $admin = User::factory()->admin()->create();
        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(EditLifecycleEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm([
                'email_key' => $template->email_key,
                'name' => 'Aangepaste naam',
                'subject' => 'Nieuw onderwerp',
                'body' => 'Nieuwe body',
                'cta_text' => 'Nieuwe CTA',
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();

        $this->assertSame('Aangepaste naam', $template->name);
        $this->assertSame('Nieuw onderwerp', $template->subject);
        $this->assertSame('Nieuwe body', $template->body);
        $this->assertSame('Nieuwe CTA', $template->cta_text);
        $this->assertFalse($template->is_active);
    }

    public function test_admin_can_send_template_test_mail_to_self_and_write_sent_log(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create([
            'name' => 'Willem Admin',
        ]);

        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(EditLifecycleEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('sendTestMail');

        Mail::assertSent(NoMaintenanceLogDay3Mail::class, function (NoMaintenanceLogDay3Mail $mail) use ($admin): bool {
            return $mail->hasTo($admin->email)
                && str_contains($mail->renderedBody, 'Hoi Willem,');
        });

        $log = LifecycleEmailLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringStartsWith('test_'.LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3.'_', $log->email_key);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertNull($log->failed_at);
        $this->assertNull($log->error_message);
    }

    public function test_failed_testmail_writes_failed_lifecycle_log_with_error_message(): void
    {
        $admin = User::factory()->admin()->create();
        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->firstOrFail();

        Config::set('mail.default', null);

        Livewire::actingAs($admin)
            ->test(EditLifecycleEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('sendTestMail');

        $log = LifecycleEmailLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringStartsWith('test_'.LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3.'_', $log->email_key);
        $this->assertSame(LifecycleEmailLog::STATUS_FAILED, $log->status);
        $this->assertNull($log->sent_at);
        $this->assertNotNull($log->failed_at);
        $this->assertStringContainsString('Mailconfig ontbreekt', (string) $log->error_message);
    }

    public function test_admin_can_open_lifecycle_email_logs_page_when_logs_table_is_missing(): void
    {
        $admin = User::factory()->admin()->create();

        Schema::dropIfExists('lifecycle_email_logs');

        $this->actingAs($admin)
            ->get('/admin/lifecycle-email-logs')
            ->assertOk()
            ->assertSeeText('Lifecycle e-maillogs zijn nog niet beschikbaar');
    }

    public function test_admin_can_open_lifecycle_email_logs_index_page_with_empty_table(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/lifecycle-email-logs')
            ->assertOk()
            ->assertSeeText('Lifecycle e-maillogs');
    }

    public function test_admin_can_open_lifecycle_email_logs_index_page_with_existing_log(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'name' => 'Rijder Test',
            'email' => 'rijder@example.com',
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Onderwerp lifecycle',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/lifecycle-email-logs')
            ->assertOk()
            ->assertSeeText('Rijder Test')
            ->assertSeeText('rijder@example.com')
            ->assertSeeText('Onderwerp lifecycle');
    }

    public function test_admin_can_export_lifecycle_email_logs_from_filament(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListLifecycleEmailLogs::class)
            ->callAction('export')
            ->assertFileDownloaded('lifecycle-email-logs-'.now()->format('Y-m-d').'.csv');
    }

    public function test_lifecycle_email_log_csv_export_contains_expected_columns_and_values(): void
    {
        $user = User::factory()->create([
            'name' => 'CSV Gebruiker',
            'email' => 'csv@example.com',
            'last_login_at' => '2026-06-01 09:30:00',
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_VEHICLE_ADDED,
            'subject' => 'CSV onderwerp',
            'status' => LifecycleEmailLog::STATUS_SKIPPED,
            'reason_skipped' => 'unsubscribed',
            'error_message' => null,
            'vehicles_count' => 0,
            'maintenance_logs_count' => 0,
            'documents_count' => 0,
            'last_login_at' => '2026-06-01 09:30:00',
            'clicked_at' => '2026-06-05 12:00:00',
            'goal_completed_at' => '2026-06-06 08:00:00',
            'skipped_at' => '2026-06-05 12:00:00',
        ]);

        $csv = app(LifecycleEmailLogExportService::class)->toCsv(
            LifecycleEmailLog::query()->with('user')->whereKey($log->getKey())
        );

        $this->assertStringContainsString('id,created_at,sent_at,user_id,user_name,user_email,email_key,status,reason_skipped,error_message,vehicles_count,maintenance_logs_count,documents_count,last_login_at,clicked_at,goal_completed_at', $csv);
        $this->assertStringContainsString('CSV Gebruiker', $csv);
        $this->assertStringContainsString('csv@example.com', $csv);
        $this->assertStringContainsString(LifecycleEmailTemplate::NO_VEHICLE_ADDED, $csv);
        $this->assertStringContainsString('unsubscribed', $csv);
    }

    public function test_lifecycle_email_log_user_display_falls_back_when_users_table_is_missing(): void
    {
        $user = User::factory()->create([
            'name' => 'Tijdelijke gebruiker',
            'email' => 'tijdelijk@example.com',
        ]);

        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Verweesde log',
            'status' => LifecycleEmailLog::STATUS_FAILED,
            'error_message' => 'Gebruiker ontbreekt',
        ]);

        Schema::dropIfExists('users');

        $this->assertSame('Onbekende gebruiker', $log->userDisplayName());
        $this->assertSame('-', $log->userDisplayEmail());
    }

    public function test_navigation_badge_does_not_crash_when_logs_table_is_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Schema::dropIfExists('lifecycle_email_logs');

        $this->assertNull(LifecycleEmailLogResource::getNavigationBadge());
    }

    public function test_filters_search_and_sort_do_not_crash_for_existing_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'name' => 'Zoeker Test',
            'email' => 'zoeker@example.com',
        ]);

        LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Zoekbare lifecycle log',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/lifecycle-email-logs?search=Zoekbare&sort=sent_at&filters[status][value]=sent')
            ->assertOk()
            ->assertSeeText('Zoeker Test')
            ->assertSeeText('Zoekbare lifecycle log');
    }

    public function test_lifecycle_email_log_resource_is_read_only(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Onderwerp',
            'status' => LifecycleEmailLog::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($admin);

        $this->assertFalse(LifecycleEmailLogResource::canCreate());
        $this->assertFalse(LifecycleEmailLogResource::canEdit($log));
        $this->assertFalse(LifecycleEmailLogResource::canDelete($log));
    }

    public function test_lifecycle_resources_are_admin_only(): void
    {
        $user = User::factory()->create();
        $log = LifecycleEmailLog::query()->create([
            'user_id' => $user->id,
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'subject' => 'Onderwerp',
            'status' => LifecycleEmailLog::STATUS_QUEUED,
        ]);

        $this->actingAs($user);

        $this->assertFalse(LifecycleEmailTemplateResource::canViewAny());
        $this->assertFalse(LifecycleEmailTemplateResource::shouldRegisterNavigation());
        $this->assertFalse(LifecycleEmailTemplateResource::canCreate());
        $this->assertFalse(LifecycleEmailLogResource::canViewAny());
        $this->assertFalse(LifecycleEmailLogResource::shouldRegisterNavigation());
        $this->assertFalse(LifecycleEmailLogResource::canCreate());
        $this->assertFalse(LifecycleEmailLogResource::canEdit($log));
        $this->assertFalse(LifecycleEmailLogResource::canDelete($log));

        $this->get('/admin/lifecycle-email-templates')->assertForbidden();
        $this->get('/admin/lifecycle-email-logs')->assertForbidden();
    }

    public function test_admin_can_open_lifecycle_template_index_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/lifecycle-email-templates')
            ->assertOk()
            ->assertSeeText('Lifecycle e-mails');
    }
}
