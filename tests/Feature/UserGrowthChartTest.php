<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Widgets\UserGrowthChart;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class UserGrowthChartTest extends TestCase
{
    public function test_growth_chart_builds_12_month_dataset(): void
    {
        CarbonImmutable::setTestNow('2026-04-22 12:00:00');

        $data = UserGrowthChart::buildMonthlyGrowthData(new Collection([
            '2025-05-03 10:00:00',
            '2025-05-18 12:00:00',
            '2026-01-10 09:00:00',
            '2026-04-01 08:00:00',
        ]));

        $this->assertCount(12, $data['labels']);
        $this->assertCount(12, $data['counts']);
        $this->assertSame(2, $data['counts'][0]);
        $this->assertSame(1, $data['counts'][8]);
        $this->assertSame(1, $data['counts'][11]);

        CarbonImmutable::setTestNow();
    }
}
