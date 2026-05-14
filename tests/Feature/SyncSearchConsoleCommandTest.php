<?php

namespace Tests\Feature;

use App\Models\SearchConsoleDailySummary;
use App\Models\SearchConsolePage;
use App\Models\SearchConsoleQuery;
use App\Services\Analytics\SearchConsoleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncSearchConsoleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_upserts_search_console_summaries_queries_and_pages(): void
    {
        $service = Mockery::mock(SearchConsoleService::class);
        $service->shouldReceive('isConfigured')->twice()->andReturn(true);
        $service->shouldReceive('fetchDailySummary')->twice()->andReturn(
            [
                'date' => '2026-05-08',
                'clicks' => 50,
                'impressions' => 700,
                'ctr' => 0.0714,
                'position' => 9.25,
            ],
            [
                'date' => '2026-05-08',
                'clicks' => 55,
                'impressions' => 710,
                'ctr' => 0.0775,
                'position' => 8.95,
            ],
        );
        $service->shouldReceive('fetchTopQueries')->twice()->andReturn(
            [[
                'date' => '2026-05-08',
                'query' => 'garagebook app',
                'clicks' => 20,
                'impressions' => 100,
                'ctr' => 0.2,
                'position' => 5.1,
            ]],
            [[
                'date' => '2026-05-08',
                'query' => 'garagebook app',
                'clicks' => 22,
                'impressions' => 110,
                'ctr' => 0.2,
                'position' => 4.9,
            ]],
        );
        $service->shouldReceive('fetchTopPages')->twice()->andReturn(
            [[
                'date' => '2026-05-08',
                'page' => 'https://garagebook.nl/blogs',
                'clicks' => 30,
                'impressions' => 300,
                'ctr' => 0.1,
                'position' => 7.4,
            ]],
            [[
                'date' => '2026-05-08',
                'page' => 'https://garagebook.nl/blogs',
                'clicks' => 35,
                'impressions' => 320,
                'ctr' => 0.1094,
                'position' => 7.1,
            ]],
        );

        $this->app->instance(SearchConsoleService::class, $service);

        $this->artisan('garagebook:sync-search-console', [
            '--from' => '2026-05-08',
            '--to' => '2026-05-08',
        ])->assertSuccessful();

        $this->artisan('garagebook:sync-search-console', [
            '--from' => '2026-05-08',
            '--to' => '2026-05-08',
        ])->assertSuccessful();

        $this->assertDatabaseHas('search_console_daily_summaries', [
            'date' => '2026-05-08',
            'clicks' => 55,
            'impressions' => 710,
        ]);

        $this->assertDatabaseHas('search_console_queries', [
            'date' => '2026-05-08',
            'query' => 'garagebook app',
            'clicks' => 22,
            'impressions' => 110,
        ]);

        $this->assertDatabaseHas('search_console_pages', [
            'date' => '2026-05-08',
            'page' => 'https://garagebook.nl/blogs',
            'clicks' => 35,
            'impressions' => 320,
        ]);

        $this->assertSame(1, SearchConsoleDailySummary::query()->count());
        $this->assertSame(1, SearchConsoleQuery::query()->count());
        $this->assertSame(1, SearchConsolePage::query()->count());
    }

    public function test_command_defaults_to_three_days_ago_when_no_date_options_are_given(): void
    {
        CarbonImmutable::setTestNow('2026-05-14 10:00:00');

        $service = Mockery::mock(SearchConsoleService::class);
        $service->shouldReceive('isConfigured')->once()->andReturn(true);
        $service->shouldReceive('fetchDailySummary')->once()->withArgs(fn ($date) => $date->toDateString() === '2026-05-11')->andReturn(null);
        $service->shouldReceive('fetchTopQueries')->once()->withArgs(fn ($date, $limit) => $date->toDateString() === '2026-05-11' && $limit === 25)->andReturn([]);
        $service->shouldReceive('fetchTopPages')->once()->withArgs(fn ($date, $limit) => $date->toDateString() === '2026-05-11' && $limit === 25)->andReturn([]);

        $this->app->instance(SearchConsoleService::class, $service);

        $this->artisan('garagebook:sync-search-console')->assertSuccessful();

        CarbonImmutable::setTestNow();
    }
}
