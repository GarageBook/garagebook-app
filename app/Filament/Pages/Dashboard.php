<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyVehicles;
use App\Filament\Widgets\MaintenanceReminders;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string
    {
        return 'Welkom terug, ' . Filament::auth()->user()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Beheer je voertuigen en voeg onderhoud toe.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MyVehicles::class,
            MaintenanceReminders::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 2;
    }
}
