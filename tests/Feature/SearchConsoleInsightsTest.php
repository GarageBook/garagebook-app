<?php

namespace Tests\Feature;

use App\Models\GscCountrySnapshot;
use App\Models\GscDateSnapshot;
use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Models\User;
use App\Services\Gsc\SearchConsoleInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/search-console-insights')
            ->assertOk()
            ->assertSeeText('Search Console Insights');

        $this->actingAs($user)
            ->get('/admin/search-console-insights')
            ->assertForbidden();
    }

    public function test_calculates_quick_wins_new_queries_and_position_changes(): void
    {
        $this->seedSnapshots();

        $dashboard = app(SearchConsoleInsightsService::class)->dashboard();

        $this->assertSame(2, $dashboard['summary']['pages']);
        $this->assertSame('/onderhoud/yamaha/mt-07', $dashboard['quick_wins'][0]['path']);
        $this->assertContains('/garage/2021-kawasaki-z650', collect($dashboard['low_ctr'])->pluck('path')->all());
        $this->assertSame('nieuwe query', $dashboard['new_queries'][0]['query']);
        $this->assertSame('yamaha mt-07 onderhoud', $dashboard['winners'][0]['query']);
        $this->assertSame('kawasaki z650 onderhoud', $dashboard['losers'][0]['query']);
        $this->assertSame('/onderhoud/yamaha/mt-07', $dashboard['vehicle_authority']['zero_click_pages'][0]['path']);
        $this->assertNotEmpty($dashboard['priorities']);
    }

    public function test_exports_search_console_insights_csv(): void
    {
        $this->seedSnapshots();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get('/admin/search-console-insights/export')
            ->assertOk()
            ->assertDownload('search-console-insights-'.now()->format('Y-m-d').'.csv');

        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('section,query,page_url,path,page_type,clicks,impressions,ctr,position,previous_position,position_delta', $csv);
        $this->assertStringContainsString('quick_wins', $csv);
        $this->assertStringContainsString('low_ctr', $csv);
        $this->assertStringContainsString('winners', $csv);
        $this->assertStringContainsString('losers', $csv);
        $this->assertStringContainsString('vehicle_authority_zero_click_pages', $csv);
    }

    public function test_countries_block_shows_top_10_by_default_and_full_list_in_details(): void
    {
        $this->seedCountriesAndDates();
        $admin = User::factory()->admin()->create();

        $dashboard = app(SearchConsoleInsightsService::class)->dashboard();
        $this->assertSame('Land 12', $dashboard['dimensions']['countries'][0]['label']);

        $html = $this->actingAs($admin)
            ->get('/admin/search-console-insights')
            ->assertOk()
            ->assertSeeText('Alle landen tonen')
            ->content();

        $this->assertSame(10, substr_count($html, 'data-gsc-dimension-row="countries-default"'));
        $this->assertSame(12, substr_count($html, 'data-gsc-dimension-row="countries-full"'));
    }

    public function test_date_trend_block_shows_compact_rows_and_full_list_in_details(): void
    {
        $this->seedCountriesAndDates();
        $admin = User::factory()->admin()->create();

        $dashboard = app(SearchConsoleInsightsService::class)->dashboard();
        $this->assertSame('2026-07-12', $dashboard['dimensions']['dates'][0]['date']);

        $html = $this->actingAs($admin)
            ->get('/admin/search-console-insights')
            ->assertOk()
            ->assertSeeText('Volledige datumtrend tonen')
            ->content();

        $this->assertSame(10, substr_count($html, 'data-gsc-dimension-row="dates-default"'));
        $this->assertSame(12, substr_count($html, 'data-gsc-dimension-row="dates-full"'));
    }

    private function seedCountriesAndDates(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            GscCountrySnapshot::query()->create([
                'date' => '2026-07-08',
                'country' => 'Land '.$i,
                'clicks' => $i,
                'impressions' => $i * 100,
                'ctr' => 0.01,
                'position' => 10,
            ]);

            GscDateSnapshot::query()->create([
                'date' => '2026-07-08',
                'data_date' => sprintf('2026-07-%02d', $i),
                'clicks' => $i,
                'impressions' => $i * 100,
                'ctr' => 0.01,
                'position' => 10,
            ]);
        }
    }

    private function seedSnapshots(): void
    {
        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/onderhoud/yamaha/mt-07',
            'path' => '/onderhoud/yamaha/mt-07',
            'clicks' => 0,
            'impressions' => 120,
            'ctr' => 0,
            'position' => 12.4,
            'page_type' => 'vehicle_authority',
        ]);

        GscPageSnapshot::query()->create([
            'date' => '2026-07-08',
            'page_url' => 'https://app.garagebook.nl/garage/2021-kawasaki-z650',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 1,
            'impressions' => 100,
            'ctr' => 0.01,
            'position' => 8.2,
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
            'path' => '/onderhoud/yamaha/mt-07',
            'clicks' => 0,
            'impressions' => 120,
            'ctr' => 0,
            'position' => 12,
            'page_type' => 'vehicle_authority',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-01',
            'query' => 'kawasaki z650 onderhoud',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 3,
            'impressions' => 90,
            'ctr' => 0.0333,
            'position' => 7,
            'page_type' => 'garage_page',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-08',
            'query' => 'kawasaki z650 onderhoud',
            'path' => '/garage/2021-kawasaki-z650',
            'clicks' => 1,
            'impressions' => 100,
            'ctr' => 0.01,
            'position' => 10,
            'page_type' => 'garage_page',
        ]);

        GscQuerySnapshot::query()->create([
            'date' => '2026-07-08',
            'query' => 'nieuwe query',
            'path' => null,
            'clicks' => 0,
            'impressions' => 70,
            'ctr' => 0,
            'position' => 15,
            'page_type' => null,
        ]);
    }
}
