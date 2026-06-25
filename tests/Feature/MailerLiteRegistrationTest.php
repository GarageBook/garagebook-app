<?php

namespace Tests\Feature;

use App\Jobs\SubscribeUserToMailerLite;
use App\Models\User;
use Filament\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            return $job->email === $user->email
                && $job->name === $user->name
                && $job->groups === ['182049396278428795'];
        });
    }

    public function test_geratel_registered_event_queues_default_and_geratel_mailerlite_groups(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');
        config()->set('services.mailerlite.geratel_group_id', '182049396278428796');

        Queue::fake();

        $user = User::factory()->create([
            'registration_source' => 'geratel',
        ]);

        Event::dispatch(new Registered($user));

        Queue::assertPushed(SubscribeUserToMailerLite::class, function (SubscribeUserToMailerLite $job) use ($user): bool {
            return $job->email === $user->email
                && $job->name === $user->name
                && $job->groups === ['182049396278428795', '182049396278428796'];
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

    public function test_geratel_registered_event_logs_warning_without_geratel_group_and_still_queues_default_group(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');
        config()->set('services.mailerlite.geratel_group_id', null);

        Log::spy();
        Queue::fake();

        $user = User::factory()->create([
            'registration_source' => 'geratel',
        ]);

        Event::dispatch(new Registered($user));

        Log::shouldHaveReceived('warning')
            ->with('Geratel registration missing MailerLite Geratel group ID.', [
                'user_id' => $user->id,
                'email' => $user->email,
            ])
            ->atLeast()
            ->once();

        Queue::assertPushed(SubscribeUserToMailerLite::class, function (SubscribeUserToMailerLite $job): bool {
            return $job->groups === ['182049396278428795'];
        });
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

        app(SubscribeUserToMailerLite::class, [
            'email' => $user->email,
            'name' => $user->name,
            'groups' => ['182049396278428795'],
        ])->handle(app(\App\Services\MailerLite\MailerLiteClient::class));

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

    public function test_mailerlite_job_accepts_existing_subscriber_success_response(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');
        config()->set('services.mailerlite.base_url', 'https://connect.mailerlite.com/api');

        Http::fake([
            'https://connect.mailerlite.com/api/subscribers' => Http::response([
                'data' => [
                    'id' => '123',
                ],
            ], 200),
        ]);

        app(SubscribeUserToMailerLite::class, [
            'email' => 'existing@example.com',
            'name' => 'Existing Subscriber',
            'groups' => ['182049396278428795', '182049396278428796'],
        ])->handle(app(\App\Services\MailerLite\MailerLiteClient::class));

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://connect.mailerlite.com/api/subscribers'
                && $request['email'] === 'existing@example.com'
                && $request['fields']['name'] === 'Existing Subscriber'
                && $request['groups'] === ['182049396278428795', '182049396278428796'];
        });
    }
}
