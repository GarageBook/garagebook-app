<?php

namespace Tests\Feature;

use App\Jobs\SubscribeUserToMailerLite;
use App\Models\User;
use App\Support\AnalyticsAttribution;
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
            return $job->email === $user->email
                && $job->name === $user->name
                && $job->groups === ['182049396278428795']
                && $job->fields === [];
        });
    }

    public function test_geratel_registered_event_queues_default_mailerlite_group_and_registration_source_field(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');

        Queue::fake();

        $user = User::factory()->create([
            'registration_source' => 'geratel',
        ]);

        Event::dispatch(new Registered($user));

        Queue::assertPushed(SubscribeUserToMailerLite::class, function (SubscribeUserToMailerLite $job) use ($user): bool {
            return $job->email === $user->email
                && $job->name === $user->name
                && $job->groups === ['182049396278428795']
                && $job->fields === ['registration_source' => 'geratel'];
        });
    }

    public function test_registered_event_queues_growth_attribution_fields_when_available(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');

        session()->start();
        session()->put(AnalyticsAttribution::SESSION_KEY, [
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'partner_slug' => 'motorclub-x',
        ]);
        Queue::fake();

        $user = User::factory()->create();

        Event::dispatch(new Registered($user));

        Queue::assertPushed(SubscribeUserToMailerLite::class, function (SubscribeUserToMailerLite $job) use ($user): bool {
            return $job->email === $user->email
                && $job->name === $user->name
                && $job->groups === ['182049396278428795']
                && $job->fields === [
                    'source' => 'partner',
                    'campaign' => 'club2026',
                    'partner_slug' => 'motorclub-x',
                ];
        });
    }

    public function test_geratel_registered_event_merges_registration_source_and_growth_attribution_fields(): void
    {
        config()->set('services.mailerlite.token', 'test-token');
        config()->set('services.mailerlite.group_id', '182049396278428795');

        session()->start();
        session()->put(AnalyticsAttribution::SESSION_KEY, [
            'source' => 'geratel',
            'campaign_slug' => 'training2026',
            'partner_slug' => 'geratel',
        ]);
        Queue::fake();

        $user = User::factory()->create([
            'registration_source' => 'geratel',
        ]);

        Event::dispatch(new Registered($user));

        Queue::assertPushed(SubscribeUserToMailerLite::class, function (SubscribeUserToMailerLite $job) use ($user): bool {
            return $job->email === $user->email
                && $job->name === $user->name
                && $job->groups === ['182049396278428795']
                && $job->fields === [
                    'registration_source' => 'geratel',
                    'source' => 'geratel',
                    'campaign' => 'training2026',
                    'partner_slug' => 'geratel',
                ];
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
            'groups' => ['182049396278428795'],
            'fields' => [
                'registration_source' => 'geratel',
                'source' => 'geratel',
                'campaign' => 'training2026',
                'partner_slug' => 'geratel',
            ],
        ])->handle(app(\App\Services\MailerLite\MailerLiteClient::class));

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://connect.mailerlite.com/api/subscribers'
                && $request['email'] === 'existing@example.com'
                && $request['fields']['name'] === 'Existing Subscriber'
                && $request['fields']['registration_source'] === 'geratel'
                && $request['fields']['source'] === 'geratel'
                && $request['fields']['campaign'] === 'training2026'
                && $request['fields']['partner_slug'] === 'geratel'
                && $request['groups'] === ['182049396278428795'];
        });
    }
}
