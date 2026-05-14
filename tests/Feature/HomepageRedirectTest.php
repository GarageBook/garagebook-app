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
        $queryString = '_gl=abc123.def456&utm_source=test&utm_medium=test&utm_campaign=crossdomain_test&utm_content=cta-top&utm_term=garagebook+app&ref=partner42';

        $this->get('/start?'.$queryString)
            ->assertStatus(302)
            ->assertRedirect('/admin/register?'.$queryString);
    }
}
