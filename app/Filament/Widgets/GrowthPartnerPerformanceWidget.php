<?php

namespace App\Filament\Widgets;

use App\Support\Growth\GrowthDashboardData;
use Filament\Widgets\Widget;

class GrowthPartnerPerformanceWidget extends Widget
{
    protected string $view = 'filament.widgets.growth-partner-performance-widget';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        return app(GrowthDashboardData::class)->partnerPerformance();
    }
}
