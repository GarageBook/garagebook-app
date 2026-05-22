<?php

namespace App\Filament\Widgets;

use App\Support\Growth\GrowthDashboardData;
use Filament\Widgets\Widget;

class GrowthLandingPageConversionWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.growth-landing-page-conversion-widget';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md' => 2,
        'xl' => 2,
        '2xl' => 2,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        return app(GrowthDashboardData::class)->landingPageConversion();
    }
}
