<?php

namespace App\Filament\Widgets;

use App\Support\Growth\GrowthDashboardData;
use Filament\Widgets\Widget;

class GrowthProductActivationFunnelWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.growth-product-activation-funnel-widget';

    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        return app(GrowthDashboardData::class)->activationFunnel();
    }
}
