<?php

namespace Tests\Feature;

use App\Jobs\SubscribeUserToMailerLite;
use App\Models\User;
use Filament\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailerLiteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_event_queues_mailerlite_subscription_job(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');

        Queue::fake();

        $user = User::factory()->create();

        Event::dispatch(new Registered($user));

        Queue::assertPushed(SubscribeUserToMailerLite::class, function (SubscribeUserToMailerLite $job) use ($user): bool {
            return $job->user->is($user);
        });
    }

    public function test_registered_event_skips_mailerlite_job_when_not_configured(): void
    {
        config()->set('services.mailerlite.token', null);
        config()->set('services.mailerlite.group_id', null);

        Queue::fake();

        $user = User::factory()->create();

        Event::dispatch(new Registered($user));

        Queue::assertNothingPushed();
    }

    public function test_mailerlite_job_posts_expected_payload(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');
        config()->set('services.mailerlite.base_url', 'https://connect.mailerlite.com/api');

        Http::fake([
            'https://connect.mailerlite.com/api/subscribers' => Http::response([
                'data' => [
                    'id' => '123',
                ],
            ], 201),
        ]);

        $user = User::factory()->create([
            'name' => 'Garage Book',
            'email' => 'hello@example.com',
        ]);

        app(SubscribeUserToMailerLite::class, ['user' => $user])->handle(app(\App\Services\MailerLite\MailerLiteClient::class));

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://connect.mailerlite.com/api/subscribers'
                && $request['email'] === 'hello@example.com'
                && $request['fields']['name'] === 'Garage Book'
                && $request['groups'] === ['182049396278428795']
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('Accept', 'application/json');
        });
    }
}
