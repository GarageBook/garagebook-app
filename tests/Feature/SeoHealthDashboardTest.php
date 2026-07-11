<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SeoHealthDashboardController;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_health_dashboard_route_uses_controller_action(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'admin/seo-health-dashboard')
            ->values();

        $this->assertCount(1, $routes);
        $this->assertSame(SeoHealthDashboardController::class, $routes[0]->getActionName());
    }

    public function test_admin_can_view_seo_health_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/seo-health-dashboard');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
        $response->assertViewIs('admin.seo-health-dashboard');
        $response->assertSee('SEO Health');
        $response->assertSeeText('Indexability overview');
        $response->assertSeeText('Sitemap health');
        $response->assertDontSee('Honda C50');
        $response->assertDontSee('/garage/honda-c50');
    }

    public function test_admin_navigation_contains_one_seo_health_item(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $navigationItems = collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->filter(fn ($item) => $item->getLabel() === 'SEO Health')
            ->values();

        $this->assertCount(1, $navigationItems);
        $this->assertSame('/admin/seo-health-dashboard', $navigationItems[0]->getUrl());
        $this->assertSame('Beheer', $navigationItems[0]->getGroup());
        $this->assertSame(192, $navigationItems[0]->getSort());

        $response = $this->get('/admin');

        $response->assertOk();

        $links = $this->seoHealthLinks($response->getContent());

        $this->assertCount(1, $links);
        $this->assertSame('/admin/seo-health-dashboard', $links[0]['href']);
        $this->assertStringContainsString('/admin/seo-health-dashboard', $links[0]['html']);
        $this->assertStringNotContainsString('wire:navigate', $links[0]['html']);
    }

    public function test_regular_user_cannot_view_seo_health_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/seo-health-dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_download_seo_health_dashboard_csv(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/seo-health-export')
            ->assertOk()
            ->assertDownload('seo-health-dashboard-'.now()->format('Y-m-d').'.csv');
    }

    public function test_regular_user_cannot_download_seo_health_dashboard_csv(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/seo-health-export')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login_for_seo_health_dashboard_csv(): void
    {
        $this->get('/admin/seo-health-export')
            ->assertRedirect('/admin/login');
    }

    public function test_seo_health_dashboard_csv_has_download_headers_and_expected_columns(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/seo-health-export')
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename=seo-health-dashboard-'.now()->format('Y-m-d').'.csv'
        );

        $csv = $response->streamedContent();

        $this->assertStringContainsString('section,metric,value,details,url,status', $csv);
        $this->assertStringContainsString('status,"SEO Health status"', $csv);
        $this->assertStringContainsString('indexability_overview,"Total Vehicles"', $csv);
    }

    /**
     * @return list<array{href:string,html:string}>
     */
    private function seoHealthLinks(string $html): array
    {
        $document = new \DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (trim(preg_replace('/\s+/', ' ', $anchor->textContent)) !== 'SEO Health') {
                continue;
            }

            $links[] = [
                'href' => $anchor->getAttribute('href'),
                'html' => $document->saveHTML($anchor),
            ];
        }

        return $links;
    }
}
