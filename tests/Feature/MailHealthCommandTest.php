<?php

namespace Tests\Feature;

use App\Support\LifecycleMailHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MailHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_health_fails_when_production_uses_log_mailer(): void
    {
        Config::set('app.env', 'production');
        Config::set('mail.default', 'log');

        $this->artisan('garagebook:mail-health')
            ->expectsOutputToContain('Production mailer is not log')
            ->assertFailed();
    }

    public function test_mail_health_fails_when_resend_api_key_is_missing_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('mail.default', 'resend');
        Config::set('services.resend.key', null);

        $this->artisan('garagebook:mail-health')
            ->expectsOutputToContain('RESEND_API_KEY present: no')
            ->assertFailed();
    }

    public function test_mail_health_fails_when_resend_class_is_not_autoloadable_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('mail.default', 'resend');
        Config::set('services.resend.key', 'test-key');

        $this->app->instance(LifecycleMailHealth::class, new LifecycleMailHealth(
            classExists: static fn (string $class): bool => false,
        ));

        $this->artisan('garagebook:mail-health')
            ->expectsOutputToContain("class_exists('Resend'): no")
            ->assertFailed();
    }
}
