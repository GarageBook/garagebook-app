<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Jobs\SubscribeUserToMailerLite;
use App\Mail\WelcomeToGarageBookMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WelcomeRegistrationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_registration_queues_exactly_one_welcome_mail_and_no_mailerlite_job(): void
    {
        Mail::fake();
        Queue::fake();
        config()->set('services.mailerlite.token', null);
        config()->set('services.mailerlite.group_id', null);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Nieuwe Gebruiker',
                'email' => 'nieuwe-gebruiker@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'nieuwe-gebruiker@example.com')->firstOrFail();

        Mail::assertQueued(WelcomeToGarageBookMail::class, 1);
        Mail::assertQueued(WelcomeToGarageBookMail::class, function (WelcomeToGarageBookMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->user->is($user);
        });
        Queue::assertNotPushed(SubscribeUserToMailerLite::class);
    }

    public function test_registration_does_not_queue_mailerlite_job_even_when_mailerlite_is_configured(): void
    {
        Mail::fake();
        Queue::fake();
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Geen MailerLite Sync',
                'email' => 'geen-mailerlite-sync@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        Mail::assertQueued(WelcomeToGarageBookMail::class, 1);
        Queue::assertNotPushed(SubscribeUserToMailerLite::class);
    }

    public function test_welcome_mail_view_renders_with_user_context(): void
    {
        config()->set('app.url', 'https://app.garagebook.test');

        $user = User::factory()->make([
            'name' => 'Willem van Veelen',
            'email' => 'willem@example.com',
        ]);

        $mail = new WelcomeToGarageBookMail($user);
        $html = $mail->render();

        $this->assertSame($user, $mail->user);
        $this->assertSame('Willem', $mail->greetingName());
        $this->assertSame('https://app.garagebook.test/admin', $mail->ctaUrl());
        $this->assertStringContainsString('Welkom bij GarageBook', $html);
        $this->assertStringContainsString('Hoi Willem,', $html);
        $this->assertStringContainsString('Eerste voertuig toevoegen', $html);
        $this->assertStringContainsString('https://app.garagebook.test/admin', $html);
    }

    public function test_welcome_mail_cta_uses_configured_app_url_without_duplicate_slashes(): void
    {
        config()->set('app.url', 'https://app.garagebook.nl/');

        $user = User::factory()->make();

        $this->assertSame('https://app.garagebook.nl/admin', (new WelcomeToGarageBookMail($user))->ctaUrl());
    }

    public function test_welcome_mail_uses_generic_greeting_without_name(): void
    {
        $user = User::factory()->make([
            'name' => '',
            'email' => 'naamloos@example.com',
        ]);

        $html = (new WelcomeToGarageBookMail($user))->render();

        $this->assertStringContainsString('Hoi,', $html);
    }
}
