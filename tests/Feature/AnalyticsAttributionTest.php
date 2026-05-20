<?php

namespace Tests\Feature;

use App\Support\AnalyticsAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_touch_utm_parameters_are_captured_in_session(): void
    {
        $this->withHeader('referer', 'https://garagebook.nl/blogs/onderhoud')
            ->get('/start?utm_source=google&utm_medium=cpc&utm_campaign=spring&utm_content=hero&utm_term=motor%20app&gclid=google-click-id')
            ->assertRedirect('/admin/register?utm_source=google&utm_medium=cpc&utm_campaign=spring&utm_content=hero&utm_term=motor%20app&gclid=google-click-id');

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
            ->assertRedirect('/admin/register?gclid=test456');

        $this->assertSame([
            'gclid' => 'test456',
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
            ->assertRedirect('/admin/register?utm_source=linkedin&utm_medium=social');

        $this->assertSame([
            'utm_source' => 'google',
            'landing_page' => '/start',
        ], session(AnalyticsAttribution::SESSION_KEY));
    }
}
