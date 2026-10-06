<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Models\OutreachProspect;
use App\Models\User;
use App\Support\AnalyticsAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_touch_utm_parameters_are_captured_in_session(): void
    {
        $this->withHeader('referer', 'https://garagebook.nl/blogs/onderhoud')
            ->get('/start?utm_source=google&utm_medium=cpc&utm_campaign=spring&utm_content=hero&utm_term=motor%20app&gclid=google-click-id&_gl=test123')
            ->assertRedirect('https://app.garagebook.nl/admin/register?utm_source=google&utm_medium=cpc&utm_campaign=spring&utm_content=hero&utm_term=motor%20app&gclid=google-click-id&_gl=test123');

        $this->assertSame([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring',
            'utm_content' => 'hero',
            'utm_term' => 'motor app',
            'gclid' => 'google-click-id',
            'landing_page' => '/start',
            'referrer' => 'https://garagebook.nl/blogs/onderhoud',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_gclid_without_utm_parameters_is_captured_in_session(): void
    {
        $this->get('/start?gclid=test456')
            ->assertRedirect('https://app.garagebook.nl/admin/register?gclid=test456');

        $this->assertSame([
            'gclid' => 'test456',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_growth_parameters_are_forwarded_and_captured_from_start(): void
    {
        $response = $this->get('/start?source=partner&campaign_slug=club2026&partner_slug=motorclub-x&utm_source=motorclub-x&utm_medium=partner&utm_campaign=club2026');

        $prospect = OutreachProspect::query()
            ->where('source', 'growth_partner')
            ->where('website', 'growth-partner:motorclub-x')
            ->firstOrFail();

        $response->assertRedirect('/demo/garage/'.$prospect->token.'?source=partner&campaign_slug=club2026&partner_slug=motorclub-x&utm_source=motorclub-x&utm_medium=partner&utm_campaign=club2026');

        $this->assertSame([
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'partner_slug' => 'motorclub-x',
            'utm_source' => 'motorclub-x',
            'utm_medium' => 'partner',
            'utm_campaign' => 'club2026',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_existing_first_touch_attribution_is_not_overwritten(): void
    {
        session()->start();
        session()->put(AnalyticsAttribution::SESSION_KEY, [
            'utm_source' => 'google',
            'landing_page' => '/start',
        ]);

        $this->get('/start?utm_source=linkedin&utm_medium=social')
            ->assertRedirect('https://app.garagebook.nl/admin/register?utm_source=linkedin&utm_medium=social');

        $this->assertSame([
            'utm_source' => 'google',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_public_first_touch_fields_override_cta_utm_values_and_persist_after_normal_registration(): void
    {
        $this->get('/admin/register?'.http_build_query([
            'utm_source' => 'garagebook.nl',
            'utm_medium' => 'website',
            'utm_campaign' => 'organic_cta',
            'attr_source' => 'search',
            'attr_medium' => 'cpc',
            'attr_campaign' => 'spring',
            'attr_referrer' => 'https://search.example/results?email=private@example.com',
            'attr_landing' => '/motor-onderhoud-app/',
            'attr_unknown' => 'ignored',
        ]))->assertOk();

        $expected = [
            'utm_source' => 'search',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring',
            'landing_page' => '/motor-onderhoud-app/',
            'referrer' => 'https://search.example',
        ];

        $this->assertSame($expected, session(AnalyticsAttribution::SESSION_KEY));

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Generic Attribution Tester',
                'email' => 'generic-attribution@example.com',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register');

        $user = User::query()->where('email', 'generic-attribution@example.com')->firstOrFail();

        $this->assertNull($user->registration_source);
        $this->assertSame('search', $user->attribution?->utm_source);
        $this->assertSame('cpc', $user->attribution?->utm_medium);
        $this->assertSame('spring', $user->attribution?->utm_campaign);
        $this->assertSame('/motor-onderhoud-app/', $user->attribution?->landing_page);
        $this->assertSame('https://search.example', $user->attribution?->referrer);
    }

    public function test_other_campaigns_use_the_same_first_touch_mapping(): void
    {
        $this->get('/register?'.http_build_query([
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'attr_source' => 'newsletter',
            'attr_medium' => 'email',
            'attr_campaign' => 'spring-club',
            'attr_landing' => '/club/',
        ]))->assertOk();

        $this->assertSame([
            'source' => 'partner',
            'campaign_slug' => 'club2026',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-club',
            'landing_page' => '/club/',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_later_campaign_parameters_do_not_replace_public_first_touch(): void
    {
        $this->get('/admin/register?attr_source=search&attr_medium=cpc&attr_landing=%2Fmotor-onderhoud-app%2F')
            ->assertOk();

        $firstTouch = session(AnalyticsAttribution::SESSION_KEY);

        $this->get('/admin/register/geratel?source=geratel&campaign_slug=geratel&attr_source=direct&attr_landing=%2Fgeratel%2F')
            ->assertOk();

        $this->assertSame($firstTouch, session(AnalyticsAttribution::SESSION_KEY));
    }

    public function test_unknown_or_manipulated_public_parameters_are_ignored_without_redirecting(): void
    {
        $this->get('/admin/register?'.http_build_query([
            'attr_source' => ['not-a-string'],
            'attr_medium' => "bad\nvalue",
            'attr_campaign' => str_repeat('x', 256),
            'attr_referrer' => 'javascript:alert(1)',
            'attr_landing' => '//evil.example',
            'attr_user_id' => '42',
        ]))->assertOk();

        $this->assertNull(session(AnalyticsAttribution::SESSION_KEY));

        $this->get('/admin/register?source=partner&attr_landing=https%3A%2F%2Fevil.example&attr_referrer=https%3A%2F%2Fuser%40evil.example')
            ->assertOk();

        $this->assertSame([
            'source' => 'partner',
            'landing_page' => '/admin/register',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }
}
