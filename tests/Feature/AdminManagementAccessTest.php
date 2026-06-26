<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Widgets\InactiveUsersTable;
use App\Filament\Resources\Users\Widgets\UserActivationStats;
use App\Filament\Resources\Users\Widgets\UserGrowthChart;
use App\Filament\Resources\Users\Widgets\UserRetentionStats;
use App\Models\Blog;
use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_open_admin_only_management_routes(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $managedUser = User::factory()->create();
        $blog = Blog::query()->create([
            'title' => 'Admin blog',
            'slug' => 'admin-blog-route',
            'content' => 'Admin content',
        ]);
        $page = Page::query()->create([
            'title' => 'Admin pagina',
            'slug' => 'admin-pagina-route',
            'content' => 'Admin pagina-inhoud',
        ]);

        $this->actingAs($user);

        $urls = [
            '/admin/users',
            '/admin/users/create',
            '/admin/users/'.$managedUser->id,
            '/admin/users/'.$managedUser->id.'/edit',
            '/admin/blogs',
            '/admin/blogs/create',
            '/admin/blogs/'.$blog->id.'/edit',
            '/admin/pages',
            '/admin/pages/create',
            '/admin/pages/'.$page->id.'/edit',
            '/admin/analytics-dashboard',
            '/admin/growth-dashboard',
            '/admin/growth-campaigns',
            '/admin/growth-campaigns/create',
            '/admin/localization-overview',
            '/admin/outreach-campaigns',
            '/admin/outreach-campaigns/create',
            '/admin/outreach-prospects',
            '/admin/outreach-prospects/create',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_open_admin_only_management_routes_and_sees_management_links(): void
    {
        $admin = User::factory()->admin()->create();

        $managedUser = User::factory()->create();
        $blog = Blog::query()->create([
            'title' => 'Admin blog',
            'slug' => 'admin-blog-admin',
            'content' => 'Admin content',
        ]);
        $page = Page::query()->create([
            'title' => 'Admin pagina',
            'slug' => 'admin-pagina-admin',
            'content' => 'Admin pagina-inhoud',
        ]);

        $this->actingAs($admin);

        $urls = [
            '/admin/users',
            '/admin/users/create',
            '/admin/users/'.$managedUser->id,
            '/admin/users/'.$managedUser->id.'/edit',
            '/admin/blogs',
            '/admin/blogs/create',
            '/admin/blogs/'.$blog->id.'/edit',
            '/admin/pages',
            '/admin/pages/create',
            '/admin/pages/'.$page->id.'/edit',
            '/admin/analytics-dashboard',
            '/admin/growth-dashboard',
            '/admin/growth-campaigns',
            '/admin/growth-campaigns/create',
            '/admin/localization-overview',
            '/admin/outreach-campaigns',
            '/admin/outreach-campaigns/create',
            '/admin/outreach-prospects',
            '/admin/outreach-prospects/create',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin')
            ->assertOk()
            ->assertSee('/admin/users', false)
            ->assertSee('/admin/blogs', false)
            ->assertSee('/admin/pages', false)
            ->assertSee('/admin/analytics-dashboard', false)
            ->assertSee('/admin/growth-dashboard', false)
            ->assertSee('/admin/growth-campaigns', false)
            ->assertSee('/admin/localization-overview', false)
            ->assertSee('/admin/outreach-campaigns', false)
            ->assertSee('/admin/outreach-prospects', false);
    }

    public function test_admin_email_match_is_case_insensitive_and_does_not_depend_on_is_admin_flag(): void
    {
        $admin = User::factory()->create([
            'email' => 'WillemVanVeelen@ICloud.Com',
            'is_admin' => false,
        ]);

        $this->assertTrue($admin->isAdmin());

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('/admin/users', false)
            ->assertSee('/admin/blogs', false)
            ->assertSee('/admin/pages', false)
            ->assertSee('/admin/analytics-dashboard', false)
            ->assertSee('/admin/growth-dashboard', false)
            ->assertSee('/admin/growth-campaigns', false)
            ->assertSee('/admin/localization-overview', false)
            ->assertSee('/admin/outreach-campaigns', false)
            ->assertSee('/admin/outreach-prospects', false);
    }

    public function test_admin_only_user_management_widgets_are_hidden_for_regular_users_and_visible_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($admin);
        $this->assertTrue(UserActivationStats::canView());
        $this->assertTrue(UserRetentionStats::canView());
        $this->assertTrue(UserGrowthChart::canView());
        $this->assertTrue(InactiveUsersTable::canView());

        $this->actingAs($user);
        $this->assertFalse(UserActivationStats::canView());
        $this->assertFalse(UserRetentionStats::canView());
        $this->assertFalse(UserGrowthChart::canView());
        $this->assertFalse(InactiveUsersTable::canView());
    }

    public function test_user_with_legacy_is_admin_flag_but_other_email_is_not_treated_as_admin(): void
    {
        $legacyFlagUser = User::factory()->create([
            'email' => 'legacy-admin@example.com',
            'is_admin' => true,
        ]);

        $this->assertFalse($legacyFlagUser->isAdmin());

        $this->actingAs($legacyFlagUser)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('/admin/users', false)
            ->assertDontSee('/admin/blogs', false)
            ->assertDontSee('/admin/pages', false)
            ->assertDontSee('/admin/analytics-dashboard', false)
            ->assertDontSee('/admin/growth-dashboard', false)
            ->assertDontSee('/admin/growth-campaigns', false)
            ->assertDontSee('/admin/localization-overview', false)
            ->assertDontSee('/admin/outreach-campaigns', false)
            ->assertDontSee('/admin/outreach-prospects', false);

        $this->actingAs($legacyFlagUser);
        $this->assertFalse(UserActivationStats::canView());
        $this->assertFalse(UserRetentionStats::canView());
        $this->assertFalse(UserGrowthChart::canView());
        $this->assertFalse(InactiveUsersTable::canView());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/blogs')->assertForbidden();
        $this->get('/admin/pages')->assertForbidden();
        $this->get('/admin/analytics-dashboard')->assertForbidden();
        $this->get('/admin/growth-dashboard')->assertForbidden();
        $this->get('/admin/growth-campaigns')->assertForbidden();
        $this->get('/admin/localization-overview')->assertForbidden();
        $this->get('/admin/outreach-campaigns')->assertForbidden();
        $this->get('/admin/outreach-prospects')->assertForbidden();
    }

    public function test_regular_user_keeps_access_to_normal_garagebook_functionality_and_sees_no_management_links(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'CBR600F',
            'nickname' => 'Eigen motor',
            'current_km' => 12000,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Mijn voertuigen')
            ->assertDontSee('/admin/users', false)
            ->assertDontSee('/admin/blogs', false)
            ->assertDontSee('/admin/pages', false)
            ->assertDontSee('/admin/analytics-dashboard', false)
            ->assertDontSee('/admin/growth-dashboard', false)
            ->assertDontSee('/admin/growth-campaigns', false)
            ->assertDontSee('/admin/localization-overview', false)
            ->assertDontSee('/admin/outreach-campaigns', false)
            ->assertDontSee('/admin/outreach-prospects', false);

        $this->actingAs($user)
            ->get('/admin/vehicles')
            ->assertOk()
            ->assertSeeText('Voertuigen')
            ->assertSeeText('Honda')
            ->assertSeeText('CBR600F');

        $this->actingAs($user)
            ->get('/admin/maintenance-logs')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/documentkluis?vehicle_id='.$vehicle->id)
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/trip-logs')
            ->assertOk();
    }
}
