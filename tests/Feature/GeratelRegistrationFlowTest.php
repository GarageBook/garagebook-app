<?php

namespace Tests\Feature;

use App\Filament\Auth\GeratelRegister;
use App\Filament\Auth\Register;
use App\Models\User;
use App\Support\AnalyticsAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeratelRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_geratel_register_page_is_publicly_available(): void
    {
        $this->get('/admin/register/geratel')
            ->assertOk()
            ->assertSee('garagebook-geratel-verified.png', false)
            ->assertDontSee('garagebook-logo.png', false)
            ->assertSee('Registreren')
            ->assertSee('Naam')
            ->assertSee('E-mailadres')
            ->assertSee('Wachtwoord')
            ->assertDontSee('inloggen op je account');
    }

    public function test_geratel_register_page_keeps_query_parameters_without_redirect_and_preserves_registration_source_flow(): void
    {
        $response = $this->get('/admin/register/geratel?utm_source=geratel&utm_medium=partner&_gl=test123&gclid=test456');

        $response
            ->assertOk()
            ->assertSee('garagebook-geratel-verified.png', false);

        $this->assertSame([
            'utm_source' => 'geratel',
            'utm_medium' => 'partner',
            'gclid' => 'test456',
            'landing_page' => '/admin/register/geratel',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_registration_via_geratel_flow_sets_registration_source(): void
    {
        Livewire::test(GeratelRegister::class)
            ->fillForm([
                'name' => 'Geratel Tester',
                'email' => 'geratel@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'geratel@example.com')->firstOrFail();

        $this->assertSame('geratel', $user->registration_source);
        $this->assertTrue($user->isGeratelUser());
        $this->assertAuthenticatedAs($user);
    }

    public function test_geratel_first_touch_survives_registration_and_is_saved_on_the_user(): void
    {
        $this->app['env'] = 'production';
        config(['analytics.ga4.measurement_id' => 'G-TEST123456']);

        $this->withHeader('referer', 'https://garagebook.nl/geratel/')
            ->get('/admin/register/geratel?source=geratel&campaign_slug=geratel&attr_landing=%2Fgeratel%2F')
            ->assertOk()
            ->assertSee('registration_started', false);

        $this->assertSame('/geratel/', session(AnalyticsAttribution::SESSION_KEY)['landing_page']);

        $this->app['env'] = 'testing';

        Livewire::test(GeratelRegister::class)
            ->fillForm([
                'name' => 'Geratel Attribution Tester',
                'email' => 'geratel-attribution@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'geratel-attribution@example.com')->firstOrFail();

        $this->assertSame('geratel', $user->registration_source);
        $this->assertSame('geratel', $user->attribution?->source);
        $this->assertSame('geratel', $user->attribution?->campaign_slug);
        $this->assertSame('/geratel/', $user->attribution?->landing_page);
    }

    public function test_geratel_registration_does_not_replace_an_existing_first_touch(): void
    {
        session()->start();
        session()->put(AnalyticsAttribution::SESSION_KEY, [
            'utm_source' => 'google',
            'landing_page' => '/motor-onderhoud-app/',
        ]);

        $this->get('/admin/register/geratel?source=geratel&campaign_slug=geratel&attr_landing=%2Fgeratel%2F')
            ->assertOk();

        $this->assertSame([
            'utm_source' => 'google',
            'landing_page' => '/motor-onderhoud-app/',
        ], session(AnalyticsAttribution::SESSION_KEY));

        Livewire::test(GeratelRegister::class)
            ->fillForm([
                'name' => 'Existing First Touch Tester',
                'email' => 'first-touch@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'first-touch@example.com')->firstOrFail();

        $this->assertSame('geratel', $user->registration_source);
        $this->assertSame('google', $user->attribution?->utm_source);
        $this->assertSame('/motor-onderhoud-app/', $user->attribution?->landing_page);
        $this->assertNull($user->attribution?->source);
    }

    public function test_all_registration_routes_accept_a_local_first_touch_path(): void
    {
        $this->get('/admin/register?source=partner&attr_landing=%2Fgeratel%2F')
            ->assertOk();

        $this->assertSame('/geratel/', session(AnalyticsAttribution::SESSION_KEY)['landing_page']);

        session()->forget(AnalyticsAttribution::SESSION_KEY);

        $this->get('/admin/register/geratel?source=geratel&attr_landing=https%3A%2F%2Fexample.com')
            ->assertOk();

        $this->assertSame('/admin/register/geratel', session(AnalyticsAttribution::SESSION_KEY)['landing_page']);
    }

    public function test_geratel_cta_keeps_an_earlier_website_source(): void
    {
        $this->get('/admin/register/geratel?campaign_slug=geratel&attr_source=google&attr_landing=%2Fmotor-onderhoud-app%2F')
            ->assertOk();

        $this->assertSame('google', session(AnalyticsAttribution::SESSION_KEY)['utm_source']);
        $this->assertArrayNotHasKey('source', session(AnalyticsAttribution::SESSION_KEY));
        $this->assertSame('geratel', session(AnalyticsAttribution::SESSION_KEY)['campaign_slug']);
        $this->assertSame('/motor-onderhoud-app/', session(AnalyticsAttribution::SESSION_KEY)['landing_page']);
    }

    public function test_registration_via_regular_flow_keeps_registration_source_null(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Regular Tester',
                'email' => 'regular@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'regular@example.com')->firstOrFail();

        $this->assertNull($user->registration_source);
        $this->assertNull($user->attribution);
        $this->assertFalse($user->isGeratelUser());
        $this->assertAuthenticatedAs($user);
    }

    public function test_geratel_logo_is_visible_for_geratel_user(): void
    {
        $user = User::factory()->create([
            'registration_source' => 'geratel',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('geratel-cursist-verified.png', false)
            ->assertSee('data-geratel-topnav-logo', false);
    }

    public function test_geratel_logo_is_not_visible_for_regular_user(): void
    {
        $user = User::factory()->create([
            'registration_source' => null,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('geratel-cursist-verified.png', false)
            ->assertDontSee('data-geratel-topnav-logo', false);
    }

    public function test_regular_register_page_remains_available_without_geratel_asset(): void
    {
        $this->get('/admin/register')
            ->assertOk()
            ->assertSee('GarageBook')
            ->assertDontSee('garagebook-geratel-verified.png', false);
    }
}
