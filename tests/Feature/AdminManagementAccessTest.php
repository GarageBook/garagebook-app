<?php

namespace Tests\Feature;

use App\Filament\Pages\AnalyticsDashboard;
use App\Filament\Pages\GrowthDashboard;
use App\Filament\Pages\LocalizationOverview;
use App\Filament\Pages\SearchConsoleImport;
use App\Filament\Pages\SearchConsoleInsights;
use App\Filament\Pages\Timeline;
use App\Filament\Pages\VehicleAuthorityDashboard;
use App\Filament\Resources\BlogResource as LegacyBlogResource;
use App\Filament\Resources\Blogs\BlogResource;
use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Filament\Resources\GrowthCampaigns\GrowthCampaignResource;
use App\Filament\Resources\GrowthProspects\GrowthProspectResource;
use App\Filament\Resources\LifecycleEmailLogs\LifecycleEmailLogResource;
use App\Filament\Resources\LifecycleEmailTemplates\LifecycleEmailTemplateResource;
use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Filament\Resources\OutreachCampaigns\OutreachCampaignResource;
use App\Filament\Resources\OutreachProspects\OutreachProspectResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\TripLogs\TripLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Widgets\InactiveUsersTable;
use App\Filament\Resources\Users\Widgets\UserActivationStats;
use App\Filament\Resources\Users\Widgets\UserGrowthChart;
use App\Filament\Resources\Users\Widgets\UserRetentionStats;
use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Filament\Resources\Vehicles\VehicleResource;
use App\Filament\Widgets\GrowthAcquisitionPerformanceWidget;
use App\Filament\Widgets\GrowthCampaignPerformanceWidget;
use App\Filament\Widgets\GrowthKpiOverviewWidget;
use App\Filament\Widgets\GrowthLandingPageConversionWidget;
use App\Filament\Widgets\GrowthPartnerPerformanceWidget;
use App\Filament\Widgets\GrowthProductActivationFunnelWidget;
use App\Filament\Widgets\GrowthProspectFollowUpWidget;
use App\Filament\Widgets\GrowthRecentActivityWidget;
use App\Filament\Widgets\GrowthSeoIntelligenceWidget;
use App\Filament\Widgets\GrowthSourceActivationWidget;
use App\Filament\Widgets\GrowthSummaryStats;
use App\Filament\Widgets\LifecycleEmailStatsWidget;
use App\Filament\Widgets\LifecycleOverviewWidget;
use App\Filament\Widgets\TopSearchQueriesWidget;
use App\Filament\Widgets\TopSeoPagesWidget;
use App\Filament\Widgets\TopVisitedPagesWidget;
use App\Models\Blog;
use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_can_open_filament_panel_for_garagebook_app(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('Mijn voertuigen')
            ->assertDontSee('/admin/users', false)
            ->assertDontSee('/admin/blogs', false)
            ->assertDontSee('/admin/pages', false)
            ->assertDontSee('/admin/analytics-dashboard', false)
            ->assertDontSee('/admin/growth-dashboard', false)
            ->assertDontSee('/admin/seo-health-dashboard', false)
            ->assertDontSee('/admin/growth-campaigns', false)
            ->assertDontSee('/admin/growth-prospects', false)
            ->assertDontSee('/admin/localization-overview', false)
            ->assertDontSee('/admin/outreach-campaigns', false)
            ->assertDontSee('/admin/outreach-prospects', false);
    }

    public function test_admin_can_open_filament_panel_and_management_routes(): void
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

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($admin);

        foreach ($this->adminOnlyUrls($managedUser, $blog, $page) as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin')
            ->assertOk()
            ->assertSee('/admin/users', false)
            ->assertSee('/admin/blogs', false)
            ->assertSee('/admin/pages', false)
            ->assertSee('/admin/analytics-dashboard', false)
            ->assertSee('/admin/growth-dashboard', false)
            ->assertSee('/admin/seo-health-dashboard', false)
            ->assertSee('/admin/growth-campaigns', false)
            ->assertSee('/admin/growth-prospects', false)
            ->assertSee('/admin/localization-overview', false)
            ->assertSee('/admin/outreach-campaigns', false)
            ->assertSee('/admin/outreach-prospects', false);
    }

    public function test_willem_email_without_is_admin_does_not_get_admin_rights(): void
    {
        $user = User::factory()->create([
            'email' => 'WillemVanVeelen@ICloud.Com',
            'is_admin' => false,
        ]);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_different_email_with_is_admin_gets_admin_rights(): void
    {
        $admin = User::factory()->create([
            'email' => 'trusted-admin@example.com',
            'is_admin' => true,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_is_admin_uses_database_boolean(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->assertFalse($user->isAdmin());

        $user->forceFill(['is_admin' => true])->save();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_regular_user_can_open_core_garagebook_flows_under_admin_panel(): void
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
            ->get(VehicleResource::getUrl('index'))
            ->assertOk()
            ->assertSeeText('Voertuigen')
            ->assertSeeText('Honda')
            ->assertSeeText('CBR600F');

        $this->actingAs($user)
            ->get(VehicleResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(MaintenanceLogResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(FuelLogResource::getUrl('index', ['vehicle_id' => $vehicle->id]))
            ->assertOk();

        $this->actingAs($user)
            ->get(TripLogResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(VehicleDocumentResource::getUrl('index', ['vehicle_id' => $vehicle->id]))
            ->assertOk();

        $this->actingAs($user)
            ->get(Timeline::getUrl(['vehicle_id' => $vehicle->id]))
            ->assertOk();
    }

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

        foreach ($this->adminOnlyUrls($managedUser, $blog, $page) as $url) {
            $this->get($url)->assertForbidden();
        }
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

    public function test_admin_only_surfaces_use_is_admin_authorization_checks(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        foreach ($this->adminOnlyResources() as $resource) {
            $this->actingAs($admin);
            $this->assertTrue($resource::canViewAny(), $resource.' should allow admins.');
            $this->assertTrue($resource::shouldRegisterNavigation(), $resource.' should register for admins.');

            $this->actingAs($user);
            $this->assertFalse($resource::canViewAny(), $resource.' should deny regular users.');
            $this->assertFalse($resource::shouldRegisterNavigation(), $resource.' should hide for regular users.');
        }

        foreach ($this->adminOnlyPages() as $page) {
            $this->actingAs($admin);
            $this->assertTrue($page::canAccess(), $page.' should allow admins.');
            $this->assertTrue($page::shouldRegisterNavigation(), $page.' should register for admins.');

            $this->actingAs($user);
            $this->assertFalse($page::canAccess(), $page.' should deny regular users.');
            $this->assertFalse($page::shouldRegisterNavigation(), $page.' should hide for regular users.');
        }

        foreach ($this->adminOnlyWidgets() as $widget) {
            $this->actingAs($admin);
            $this->assertTrue($widget::canView(), $widget.' should allow admins.');

            $this->actingAs($user);
            $this->assertFalse($widget::canView(), $widget.' should deny regular users.');
        }
    }

    /** @return list<string> */
    private function adminOnlyUrls(User $managedUser, Blog $blog, Page $page): array
    {
        return [
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
            '/admin/seo-health-dashboard',
            '/admin/growth-campaigns',
            '/admin/growth-campaigns/create',
            '/admin/growth-prospects',
            '/admin/growth-prospects/create',
            '/admin/growth-prospects/import',
            '/admin/localization-overview',
            '/admin/outreach-campaigns',
            '/admin/outreach-campaigns/create',
            '/admin/outreach-prospects',
            '/admin/outreach-prospects/create',
            '/admin/lifecycle-email-logs',
            '/admin/lifecycle-email-templates',
        ];
    }

    /** @return list<class-string> */
    private function adminOnlyResources(): array
    {
        return [
            BlogResource::class,
            LegacyBlogResource::class,
            PageResource::class,
            UserResource::class,
            GrowthCampaignResource::class,
            GrowthProspectResource::class,
            LifecycleEmailLogResource::class,
            LifecycleEmailTemplateResource::class,
            OutreachCampaignResource::class,
            OutreachProspectResource::class,
        ];
    }

    /** @return list<class-string> */
    private function adminOnlyPages(): array
    {
        return [
            AnalyticsDashboard::class,
            GrowthDashboard::class,
            LocalizationOverview::class,
            SearchConsoleImport::class,
            SearchConsoleInsights::class,
            VehicleAuthorityDashboard::class,
        ];
    }

    /** @return list<class-string> */
    private function adminOnlyWidgets(): array
    {
        return [
            GrowthAcquisitionPerformanceWidget::class,
            GrowthCampaignPerformanceWidget::class,
            GrowthKpiOverviewWidget::class,
            GrowthLandingPageConversionWidget::class,
            GrowthPartnerPerformanceWidget::class,
            GrowthProductActivationFunnelWidget::class,
            GrowthProspectFollowUpWidget::class,
            GrowthRecentActivityWidget::class,
            GrowthSeoIntelligenceWidget::class,
            GrowthSourceActivationWidget::class,
            GrowthSummaryStats::class,
            LifecycleEmailStatsWidget::class,
            LifecycleOverviewWidget::class,
            TopSearchQueriesWidget::class,
            TopSeoPagesWidget::class,
            TopVisitedPagesWidget::class,
        ];
    }
}
