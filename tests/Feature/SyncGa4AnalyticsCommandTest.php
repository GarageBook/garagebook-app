<?php

namespace Tests\Feature;

use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsTopPage;
use App\Services\Analytics\Ga4AnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncGa4AnalyticsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_upserts_ga4_daily_summary_and_top_pages(): void
    {
        $service = Mockery::mock(Ga4AnalyticsService::class);
        $service->shouldReceive('isConfigured')->twice()->andReturn(true);
        $service->shouldReceive('fetchDailySummary')->twice()->andReturn(
            [
                'date' => '2026-05-10',
                'users' => 120,
                'sessions' => 180,
                'screen_page_views' => 640,
                'event_count' => 820,
                'conversions' => 12,
            ],
            [
                'date' => '2026-05-10',
                'users' => 140,
                'sessions' => 200,
                'screen_page_views' => 700,
                'event_count' => 860,
                'conversions' => 16,
            ],
        );
        $service->shouldReceive('fetchTopPages')->twice()->andReturn(
            [[
                'date' => '2026-05-10',
                'page_path' => '/blog',
                'page_title' => 'Blog',
                'views' => 80,
                'users' => 60,
            ]],
            [[
                'date' => '2026-05-10',
                'page_path' => '/blog',
                'page_title' => 'Blog overzicht',
                'views' => 95,
                'users' => 70,
            ]],
        );

        $this->app->instance(Ga4AnalyticsService::class, $service);

        $this->artisan('garagebook:sync-ga4-analytics', [
            '--from' => '2026-05-10',
            '--to' => '2026-05-10',
        ])->assertSuccessful();

        $this->artisan('garagebook:sync-ga4-analytics', [
            '--from' => '2026-05-10',
            '--to' => '2026-05-10',
        ])->assertSuccessful();

        $this->assertDatabaseHas('analytics_daily_summaries', [
            'date' => '2026-05-10',
            'users' => 140,
            'sessions' => 200,
            'screen_page_views' => 700,
            'event_count' => 860,
            'conversions' => 16,
        ]);

        $this->assertDatabaseHas('analytics_top_pages', [
            'date' => '2026-05-10',
            'page_path' => '/blog',
            'page_title' => 'Blog overzicht',
            'views' => 95,
            'users' => 70,
        ]);

        $this->assertSame(1, AnalyticsDailySummary::query()->count());
        $this->assertSame(1, AnalyticsTopPage::query()->count());
    }

    public function test_command_defaults_to_yesterday_when_no_date_options_are_given(): void
    {
        CarbonImmutable::setTestNow('2026-05-14 10:00:00');

        $service = Mockery::mock(Ga4AnalyticsService::class);
        $service->shouldReceive('isConfigured')->once()->andReturn(true);
        $service->shouldReceive('fetchDailySummary')->once()->withArgs(fn ($date) => $date->toDateString() === '2026-05-13')->andReturn(null);
        $service->shouldReceive('fetchTopPages')->once()->withArgs(fn ($date, $limit) => $date->toDateString() === '2026-05-13' && $limit === 25)->andReturn([]);

        $this->app->instance(Ga4AnalyticsService::class, $service);

        $this->artisan('garagebook:sync-ga4-analytics')->assertSuccessful();

        CarbonImmutable::setTestNow();
    }
}
