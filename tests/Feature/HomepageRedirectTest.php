<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomepageRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_public_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('GarageBook');
    }

    public function test_authenticated_user_is_redirected_to_dashboard_from_homepage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/admin');
    }

    public function test_public_website_path_redirects_to_homepage(): void
    {
        $this->get('/website')
            ->assertRedirect('/');
    }

    public function test_start_redirect_preserves_utm_query_parameters(): void
    {
        $response = $this->get('/start?utm_source=google&utm_medium=cpc&utm_campaign=brand');

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringContainsString('/admin/register?', $location);
        $this->assertTrue(Str::contains($location, 'utm_source=google'));
        $this->assertTrue(Str::contains($location, 'utm_medium=cpc'));
        $this->assertTrue(Str::contains($location, 'utm_campaign=brand'));
    }
}
