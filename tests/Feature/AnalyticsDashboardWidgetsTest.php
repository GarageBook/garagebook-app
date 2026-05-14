<?php

namespace Tests\Feature;

use App\Filament\Widgets\GrowthSummaryStats;
use App\Filament\Widgets\TopSearchQueriesWidget;
use App\Filament\Widgets\TopSeoPagesWidget;
use App\Filament\Widgets\TopVisitedPagesWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_empty_state_when_no_analytics_data_is_available(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(GrowthSummaryStats::class)
            ->assertSeeText('Nog geen analyticsdata beschikbaar. Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.');

        Livewire::test(TopVisitedPagesWidget::class)
            ->assertSeeText('Nog geen analyticsdata beschikbaar.')
            ->assertSeeText('Draai eerst php artisan garagebook:sync-ga4-analytics en php artisan garagebook:sync-search-console.');
    }

    public function test_widgets_are_only_visible_to_admins(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

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
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

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

    public function test_grouped_search_queries_widget_query_does_not_append_qualified_primary_key_sort(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

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
