<?php

namespace Tests\Feature;

use App\Filament\Widgets\GrowthSummaryStats;
use App\Filament\Widgets\TopSearchQueriesWidget;
use App\Filament\Widgets\TopSeoPagesWidget;
use App\Filament\Widgets\TopVisitedPagesWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
