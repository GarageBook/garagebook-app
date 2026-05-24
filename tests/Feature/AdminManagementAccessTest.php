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
            '/admin/users/' . $managedUser->id,
            '/admin/users/' . $managedUser->id . '/edit',
            '/admin/blogs',
            '/admin/blogs/create',
            '/admin/blogs/' . $blog->id . '/edit',
            '/admin/pages',
            '/admin/pages/create',
            '/admin/pages/' . $page->id . '/edit',
            '/admin/analytics-dashboard',
            '/admin/growth-dashboard',
            '/admin/localization-overview',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_open_admin_only_management_routes(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

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
            '/admin/users/' . $managedUser->id,
            '/admin/users/' . $managedUser->id . '/edit',
            '/admin/blogs',
            '/admin/blogs/create',
            '/admin/blogs/' . $blog->id . '/edit',
            '/admin/pages',
            '/admin/pages/create',
            '/admin/pages/' . $page->id . '/edit',
            '/admin/analytics-dashboard',
            '/admin/growth-dashboard',
            '/admin/localization-overview',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_only_user_management_widgets_are_hidden_for_regular_users_and_visible_for_admins(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
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

    public function test_regular_user_keeps_access_to_normal_garagebook_functionality(): void
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
            ->assertSeeText('Mijn voertuigen');

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
            ->get('/admin/documentkluis?vehicle_id=' . $vehicle->id)
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/trip-logs')
            ->assertOk();
    }
}
