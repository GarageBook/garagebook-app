<?php

namespace Tests\Feature;

use App\Models\LifecycleEmailTemplate;
use Database\Seeders\LifecycleEmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LifecycleEmailTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_default_lifecycle_templates(): void
    {
        $this->seed(LifecycleEmailTemplateSeeder::class);

        $expected = [
            LifecycleEmailTemplate::NO_VEHICLE_DAY2,
            LifecycleEmailTemplate::NO_VEHICLE_ADDED,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_14,
            LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_30,
            LifecycleEmailTemplate::AFTER_FIRST_MAINTENANCE_LOG,
            LifecycleEmailTemplate::INACTIVE_USER_RETURN,
        ];

        $actual = LifecycleEmailTemplate::query()->pluck('email_key')->all();
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_seeder_updates_existing_customized_templates(): void
    {
        LifecycleEmailTemplate::query()->create([
            'email_key' => LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3,
            'name' => 'Eigen naam',
            'subject' => 'Eigen onderwerp',
            'body' => 'Eigen body',
            'cta_text' => 'Eigen CTA',
            'is_active' => false,
        ]);

        $this->seed(LifecycleEmailTemplateSeeder::class);

        $template = LifecycleEmailTemplate::query()->where('email_key', LifecycleEmailTemplate::NO_MAINTENANCE_LOG_DAY_3)->firstOrFail();

        $this->assertSame('Geen onderhoudslog - dag 3', $template->name);
        $this->assertSame('Leg je eerste onderhoud vast', $template->subject);
        $this->assertStringContainsString('Voeg nu de laatste beurt', $template->body);
        $this->assertSame('Onderhoud toevoegen', $template->cta_text);
        $this->assertTrue($template->is_active);
    }

    public function test_users_table_has_lifecycle_unsubscribe_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'lifecycle_emails_unsubscribed_at'));
    }
}
