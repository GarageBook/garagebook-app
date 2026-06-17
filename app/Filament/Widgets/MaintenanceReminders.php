<?php

namespace App\Filament\Widgets;

use App\Services\ReminderService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class MaintenanceReminders extends Widget
{
    protected string $view = 'filament.widgets.maintenance-reminders';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        return app(ReminderService::class)->getWidgetData(userId: Filament::auth()->id());
    }
}
