<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_dashboard_no_longer_shows_analytics_widgets(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Mijn voertuigen')
            ->assertDontSee('Top Search Queries')
            ->assertDontSee('Top SEO Pages')
            ->assertDontSee('Top Visited Pages')
            ->assertDontSee('GA4 users laatste 30 dagen');
    }

    public function test_admin_sees_analytics_dashboard_navigation_and_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Analytics dashboard');

        $this->actingAs($admin)
            ->get('/admin/analytics-dashboard')
            ->assertOk()
            ->assertSee('Analytics dashboard')
            ->assertSee('Top Search Queries')
            ->assertSee('Top SEO Pages')
            ->assertSee('Top Visited Pages')
            ->assertSee('Nog geen analyticsdata beschikbaar.');
    }

    public function test_regular_users_cannot_access_analytics_dashboard_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/analytics-dashboard')
            ->assertForbidden();
    }
}
