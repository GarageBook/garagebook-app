<?php

namespace Tests\Feature;

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
}
