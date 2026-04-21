<?php

namespace App\Filament\Widgets;

use App\Services\ReminderService;
use Filament\Widgets\Widget;

class MaintenanceReminders extends Widget
{
    protected string $view = 'filament.widgets.maintenance-reminders';

    protected int | string | array $columnSpan = 1;

    public function getViewData(): array
    {
        return [
            'reminders' => app(ReminderService::class)->getWidgetItems(),
        ];
    }
}
