<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardActions;
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

    public function getHeaderWidgets(): array
    {
        return [
            DashboardActions::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 1;
    }
}