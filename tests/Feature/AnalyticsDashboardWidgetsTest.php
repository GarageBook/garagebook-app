<?php

namespace Tests\Feature;

use App\Filament\Widgets\GrowthSummaryStats;
use App\Filament\Widgets\TopSearchQueriesWidget;
use App\Filament\Widgets\TopSeoPagesWidget;
use App\Filament\Widgets\TopVisitedPagesWidget;
use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsTopPage;
use App\Models\MaintenanceLog;
use App\Models\SearchConsoleDailySummary;
use App\Models\SearchConsolePage;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_empty_state_when_no_analytics_data_is_available(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(GrowthSummaryStats::class)
            ->assertSeeText('Nog geen analyticsdata beschikbaar. Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.');

        Livewire::test(TopVisitedPagesWidget::class)
            ->assertSeeText('Nog geen gesynchroniseerde analyticsdata beschikbaar.')
            ->assertSeeText('Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.');
    }

    public function test_widgets_are_only_visible_to_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($admin);

        $this->assertTrue(GrowthSummaryStats::canView());
        $this->assertTrue(TopSearchQueriesWidget::canView());
        $this->assertTrue(TopSeoPagesWidget::canView());
        $this->assertTrue(TopVisitedPagesWidget::canView());

        $this->actingAs($user);

        $this->assertFalse(GrowthSummaryStats::canView());
        $this->assertFalse(TopSearchQueriesWidget::canView());
        $this->assertFalse(TopSeoPagesWidget::canView());
        $this->assertFalse(TopVisitedPagesWidget::canView());
    }

    public function test_admin_dashboard_still_loads_when_analytics_tables_are_missing(): void
    {
        $admin = User::factory()->admin()->create();

        Schema::dropIfExists('analytics_daily_summaries');
        Schema::dropIfExists('analytics_top_pages');
        Schema::dropIfExists('search_console_daily_summaries');
        Schema::dropIfExists('search_console_queries');
        Schema::dropIfExists('search_console_pages');

        $this->actingAs($admin);

        $this->assertFalse(GrowthSummaryStats::canView());
        $this->assertFalse(TopSearchQueriesWidget::canView());
        $this->assertFalse(TopSeoPagesWidget::canView());
        $this->assertFalse(TopVisitedPagesWidget::canView());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Mijn voertuigen');
    }

    public function test_analytics_overview_shows_primary_product_kpis_without_analytics_events(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $vehicle = Vehicle::query()->create([
            'user_id' => $user->id,
            'brand' => 'Honda',
            'model' => 'Civic',
            'is_public' => true,
        ]);

        MaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Inspection',
            'maintenance_date' => today(),
            'km_reading' => 12345,
        ]);

        $this->actingAs($admin);

        Livewire::test(GrowthSummaryStats::class)
            ->assertSeeText('Users totaal')
            ->assertSeeText('Voertuigen totaal')
            ->assertSeeText('Onderhoudslogs totaal')
            ->assertSeeText('Publieke voertuigen')
            ->assertSeeText('Nog geen analyticsdata beschikbaar.');
    }

    public function test_stale_analytics_summary_data_is_still_shown_with_sync_warning(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        try {
            $admin = User::factory()->admin()->create();

            AnalyticsDailySummary::query()->create([
                'date' => '2026-05-18',
                'users' => 11,
                'sessions' => 13,
                'screen_page_views' => 31,
                'event_count' => 41,
                'conversions' => 0,
            ]);

            AnalyticsDailySummary::query()->create([
                'date' => '2026-04-18',
                'users' => 999,
                'sessions' => 999,
                'screen_page_views' => 999,
                'event_count' => 999,
                'conversions' => 0,
            ]);

            SearchConsoleDailySummary::query()->create([
                'date' => '2026-05-18',
                'clicks' => 7,
                'impressions' => 70,
                'ctr' => 0.1,
                'position' => 4.2,
            ]);

            $this->actingAs($admin);

            Livewire::test(GrowthSummaryStats::class)
                ->assertSeeText('Data t/m 18-05-2026')
                ->assertSeeText('Analytics-sync lijkt achter te lopen')
                ->assertSeeText('11')
                ->assertSeeText('31')
                ->assertSeeText('7')
                ->assertDontSeeText('999');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_analytics_tables_use_latest_available_thirty_day_window(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');

        try {
            $admin = User::factory()->admin()->create();

            SearchConsoleQuery::query()->create([
                'date' => '2026-05-18',
                'query' => 'garagebook onderhoud',
                'clicks' => 12,
                'impressions' => 120,
                'ctr' => 0.1,
                'position' => 3.2,
            ]);

            SearchConsoleQuery::query()->create([
                'date' => '2026-04-18',
                'query' => 'oude query buiten window',
                'clicks' => 999,
                'impressions' => 999,
                'ctr' => 1,
                'position' => 1,
            ]);

            SearchConsolePage::query()->create([
                'date' => '2026-05-18',
                'page' => 'https://garagebook.nl/actueel',
                'clicks' => 9,
                'impressions' => 90,
                'ctr' => 0.1,
                'position' => 5,
            ]);

            AnalyticsTopPage::query()->create([
                'date' => '2026-05-18',
                'page_path' => '/actueel',
                'page_title' => 'Actueel',
                'views' => 33,
                'users' => 22,
            ]);

            AnalyticsTopPage::query()->create([
                'date' => '2026-04-18',
                'page_path' => '/oud',
                'page_title' => 'Oud',
                'views' => 999,
                'users' => 999,
            ]);

            $this->actingAs($admin);

            Livewire::test(TopSearchQueriesWidget::class)
                ->assertSeeText('Data t/m 18-05-2026')
                ->assertSeeText('garagebook onderhoud')
                ->assertDontSeeText('oude query buiten window');

            Livewire::test(TopSeoPagesWidget::class)
                ->assertSeeText('Data t/m 18-05-2026')
                ->assertSeeText('https://garagebook.nl/actueel');

            Livewire::test(TopVisitedPagesWidget::class)
                ->assertSeeText('Data t/m 18-05-2026')
                ->assertSeeText('/actueel')
                ->assertDontSeeText('/oud');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_grouped_search_queries_widget_query_does_not_append_qualified_primary_key_sort(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $sql = strtolower(
            Livewire::test(TopSearchQueriesWidget::class)
                ->invade()
                ->getFilteredSortedTableQuery()
                ->toSql()
        );

        $this->assertStringContainsString('group by', $sql);
        $this->assertStringContainsString('order by', $sql);
        $this->assertMatchesRegularExpression('/order by\\s+[\"`]?clicks[\"`]?\\s+desc/', $sql);
        $this->assertStringNotContainsString('search_console_queries.id', $sql);
    }
}
