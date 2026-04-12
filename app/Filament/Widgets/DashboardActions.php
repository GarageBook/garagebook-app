<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardActions extends Widget
{
    protected string $view = 'filament.widgets.dashboard-actions';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;
}