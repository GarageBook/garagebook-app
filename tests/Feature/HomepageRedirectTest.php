<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_start_redirect_preserves_all_query_parameters(): void
    {
        $queryString = 'utm_source=test&utm_medium=referral&utm_campaign=debug&utm_content=hero&utm_term=garagebook&_gl=test123&gclid=test456';

        $this->get('/start?'.$queryString)
            ->assertStatus(302)
            ->assertRedirect('/admin/register?'.$queryString);
    }

    public function test_start_redirect_drops_non_whitelisted_query_parameters(): void
    {
        $this->get('/start?utm_source=test&utm_medium=referral&foo=bar')
            ->assertStatus(302)
            ->assertRedirect('/admin/register?utm_source=test&utm_medium=referral');
    }
}
