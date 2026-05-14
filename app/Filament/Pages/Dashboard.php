<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CostBreakdownByVehicleChart;
use App\Filament\Widgets\CumulativeCostTrendChart;
use App\Filament\Widgets\MyVehicles;
use App\Filament\Widgets\FuelConsumptionOverview;
use App\Filament\Widgets\FuelConsumptionTrendChart;
use App\Filament\Widgets\MaintenanceActivityChart;
use App\Filament\Widgets\MaintenanceCosts;
use App\Filament\Widgets\MaintenanceReminders;
use App\Support\AnalyticsEventTracker;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user) {
            app(AnalyticsEventTracker::class)->queueDashboardViewed($user);
        }
    }

    public function getHeading(): string
    {
        return __('dashboard.welcome_back', [
            'name' => Filament::auth()->user()->name,
        ]);
    }

    public function getSubheading(): ?string
    {
        return __('dashboard.subheading');
    }

    public function headerWidgets(Schema $schema): Schema
    {
        $hasFuelLogs = Filament::auth()->user()?->vehicles()->whereHas('fuelLogs')->exists();

        $secondaryWidgets = [
            Livewire::make(MaintenanceReminders::class),
            Livewire::make(MaintenanceCosts::class),
        ];

        if ($hasFuelLogs) {
            $secondaryWidgets[] = Livewire::make(FuelConsumptionOverview::class);
        }

        $chartWidgets = [
            Livewire::make(CostBreakdownByVehicleChart::class),
            Livewire::make(MaintenanceActivityChart::class),
            Livewire::make(CumulativeCostTrendChart::class),
        ];

        if ($hasFuelLogs) {
            $chartWidgets[] = Livewire::make(FuelConsumptionTrendChart::class);
        }

        return $schema->components([
            Grid::make([
                'md' => 2,
            ])->schema([
                Livewire::make(MyVehicles::class),
                Grid::make(1)->schema($secondaryWidgets),
            ]),
            Grid::make([
                'md' => 2,
            ])->schema($chartWidgets),
        ]);
    }
}
