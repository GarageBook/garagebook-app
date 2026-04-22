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

    public function test_authenticated_user_can_still_open_public_website(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/website')
            ->assertOk()
            ->assertSee('GarageBook');
    }
}
