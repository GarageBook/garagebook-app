<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoHealthDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_seo_health_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/seo-health-dashboard')
            ->assertOk()
            ->assertSeeText('SEO Health')
            ->assertSeeText('Indexability overview')
            ->assertSeeText('Sitemap health');
    }

    public function test_seo_health_dashboard_admin_route_returns_ok_without_redirect(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/seo-health-dashboard');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
    }

    public function test_seo_health_navigation_item_points_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $navigationItem = collect(SeoHealthDashboard::getNavigationItems())
            ->first(fn ($item) => $item->getLabel() === 'SEO Health');

        $this->assertNotNull($navigationItem);
        $this->assertSame(SeoHealthDashboard::getUrl(panel: 'admin'), $navigationItem->getUrl());
        $this->assertStringContainsString('/admin/seo-health-dashboard', $navigationItem->getUrl());
        $this->assertStringNotContainsString('/garage/', $navigationItem->getUrl());
    }

    public function test_admin_navigation_html_links_seo_health_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();

        $links = $this->seoHealthLinks($response->getContent());

        $this->assertCount(1, $links, 'Expected exactly one rendered SEO Health navigation link: '.json_encode($links));
        $this->assertStringEndsWith('/admin/seo-health-dashboard', $links[0]);
        $this->assertStringNotContainsString('/garage/', $links[0]);
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
            ->get('/admin/seo-health-dashboard/export')
            ->assertOk()
            ->assertDownload('seo-health-dashboard-'.now()->format('Y-m-d').'.csv');
    }

    public function test_regular_user_cannot_download_seo_health_dashboard_csv(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin/seo-health-dashboard/export')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login_for_seo_health_dashboard_csv(): void
    {
        $this->get('/admin/seo-health-dashboard/export')
            ->assertRedirect('/admin/login');
    }

    public function test_seo_health_dashboard_csv_has_download_headers_and_expected_columns(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/seo-health-dashboard/export')
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
     * @return list<string>
     */
    private function seoHealthLinks(string $html): array
    {
        $document = new \DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            if (trim(preg_replace('/\\s+/', ' ', $anchor->textContent)) !== 'SEO Health') {
                continue;
            }

            $links[] = $anchor->getAttribute('href');
        }

        return $links;
    }
}
