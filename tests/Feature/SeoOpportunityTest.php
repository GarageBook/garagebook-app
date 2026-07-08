<?php

namespace Tests\Feature;

use App\Mail\WeeklyGrowthReportMail;
use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Models\SeoOpportunity;
use App\Models\User;
use App\Services\Gsc\SeoOpportunityService;
use App\Support\Growth\GrowthDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoOpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_rule_based_seo_opportunities_and_scores_them(): void
    {
        $this->seedGscSnapshots();

        $rows = app(SeoOpportunityService::class)->refreshLatest();
        $types = collect($rows)->pluck('type')->all();

        $this->assertContains(SeoOpportunityService::TYPE_LOW_CTR, $types);
        $this->assertContains(SeoOpportunityService::TYPE_POSITION_8_20, $types);
        $this->assertContains(SeoOpportunityService::TYPE_NEW_KEYWORD, $types);
        $this->assertContains(SeoOpportunityService::TYPE_VEHICLE_AUTHORITY, $types);
        $this->assertContains(SeoOpportunityService::TYPE_RISING, $types);
        $this->assertContains(SeoOpportunityService::TYPE_DECLINING, $types);

        $top = collect($rows)->first();

        $this->assertIsInt($top['impact_score']);
        $this->assertGreaterThanOrEqual(0, $top['impact_score']);
        $this->assertLessThanOrEqual(100, $top['impact_score']);
        $this->assertNotEmpty($top['recommended_action']);
    }

    public function test_refresh_persists_daily_opportunity_history(): void
    {
        $this->seedGscSnapshots();

        app(SeoOpportunityService::class)->refreshLatest();

        $this->assertDatabaseHas('seo_opportunities', [
            'date' => '2026-07-08 00:00:00',
            'type' => SeoOpportunityService::TYPE_VEHICLE_AUTHORITY,
            'path' => '/onderhoud/yamaha/mt-07',
            'recommended_action' => 'Modelpagina uitbreiden.',
        ]);

        $this->assertGreaterThanOrEqual(6, SeoOpportunity::query()->whereDate('date', '2026-07-08')->count());
    }

    public function test_filters_opportunities_by_type_page_type_score_brand_and_date(): void
    {
        $this->seedGscSnapshots();

        $service = app(SeoOpportunityService::class);
        $rows = $service->top([
            'type' => SeoOpportunityService::TYPE_VEHICLE_AUTHORITY,
            'page_type' => 'vehicle_authority',
            'min_score' => 50,
            'brand' => 'yamaha',
            'date' => '2026-07-08',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(SeoOpportunityService::TYPE_VEHICLE_AUTHORITY, $rows[0]['type']);
        $this->assertSame('/onderhoud/yamaha/mt-07', $rows[0]['path']);
        $this->assertSame('yamaha', $rows[0]['brand']);
    }

    public function test_dashboard_shows_top_seo_opportunities(): void
    {
        $this->seedGscSnapshots();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/search-console-insights?type='.SeoOpportunityService::TYPE_VEHICLE_AUTHORITY)
            ->assertOk()
            ->assertSeeText('Top SEO Opportunities')
            ->assertSeeText('Modelpagina uitbreiden')
            ->assertSeeText('/onderhoud/yamaha/mt-07');
    }

    public function test_exports_seo_opportunities_csv(): void
    {
        $this->seedGscSnapshots();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/search-console-insights/opportunities/export?type='.SeoOpportunityService::TYPE_VEHICLE_AUTHORITY)
            ->assertOk()
            ->assertDownload('seo-opportunities-'.now()->format('Y-m-d').'.csv');

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('date,score,priority,effort,type,title,description,page_url,path,query,page_type,brand,recommended_action', $csv);
        $this->assertStringContainsString('vehicle_authority_zero_clicks', $csv);
        $this->assertStringContainsString('Modelpagina uitbreiden', $csv);
    }

    public function test_weekly_report_includes_top_ten_seo_opportunities(): void
    {
        $this->seedGscSnapshots();

        $report = app(GrowthDashboardData::class)->weeklyGrowthReport();

        $this->assertNotEmpty($report['seo_opportunities']);
        $this->assertLessThanOrEqual(10, count($report['seo_opportunities']));

        $mail = new WeeklyGrowthReportMail($report);
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($mail->render())));

        $this->assertStringContainsString('Top 10 SEO-kansen', $text);
        $this->assertStringContainsString('Modelpagina uitbreiden', $text);
    }

    private function seedGscSnapshots(): void
    {
        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/onderhoud/yamaha/mt-07',
            'path' => '/onderhoud/yamaha/mt-07',
            'clicks' => 0,
            'impressions' => 180,
            'ctr' => 0,
            'position' => 12,
            'page_type' => 'vehicle_authority',
        ]);

        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/garage/2021-kawasaki-z650',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 1,
            'impressions' => 140,
            'ctr' => 0.0071,
            'position' => 9,
            'page_type' => 'garage_page',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-01',
            'query' => 'yamaha mt-07 onderhoud',
            'path' => '/onderhoud/yamaha/mt-07',
            'clicks' => 0,
            'impressions' => 80,
            'ctr' => 0,
            'position' => 18,
            'page_type' => 'vehicle_authority',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-08',
            'query' => 'yamaha mt-07 onderhoud',
            'page_url' => 'https://app.garagebook.nl/onderhoud/yamaha/mt-07',
            'path' => '/onderhoud/yamaha/mt-07',
            'clicks' => 0,
            'impressions' => 180,
            'ctr' => 0,
            'position' => 10,
            'page_type' => 'vehicle_authority',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-01',
            'query' => 'kawasaki z650 onderhoud',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 3,
            'impressions' => 90,
            'ctr' => 0.0333,
            'position' => 5,
            'page_type' => 'garage_page',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-08',
            'query' => 'kawasaki z650 onderhoud',
            'page_url' => 'https://app.garagebook.nl/garage/2021-kawasaki-z650',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 1,
            'impressions' => 140,
            'ctr' => 0.0071,
            'position' => 12,
            'page_type' => 'garage_page',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-08',
            'query' => 'beste onderhoudsboek motor',
            'path' => null,
            'clicks' => 0,
            'impressions' => 90,
            'ctr' => 0,
            'position' => 16,
            'page_type' => null,
        ]);
    }
}
