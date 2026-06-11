<?php

namespace Tests\Feature;

use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use App\Filament\Resources\LifecycleEmailTemplates\LifecycleEmailTemplateResource;
use App\Filament\Resources\LifecycleEmailTemplates\Pages\EditLifecycleEmailTemplate;
use App\Models\LifecycleEmailLog;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $template = LifecycleEmailTemplate::query()->firstOrFail();
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
}
