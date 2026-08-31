<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CostBreakdownByVehicleChart;
use App\Filament\Widgets\CumulativeCostTrendChart;
use App\Filament\Widgets\DashboardActions;
use App\Filament\Widgets\DashboardOnboardingWidget;
use App\Filament\Widgets\FuelConsumptionOverview;
use App\Filament\Widgets\FuelConsumptionTrendChart;
use App\Filament\Widgets\MaintenanceActivityChart;
use App\Filament\Widgets\MaintenanceCosts;
use App\Filament\Widgets\MaintenanceReminders;
use App\Filament\Widgets\MyVehicles;
use App\Filament\Widgets\PublicVehiclePagesWidget;
use App\Services\Outreach\OutreachDemoService;
use App\Support\AnalyticsEventTracker;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
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
        if ($this->marktplaatsDemoContext() !== null) {
            return 'Voorbeeld GarageBook';
        }

        return __('dashboard.welcome_back', [
            'name' => Filament::auth()->user()->name,
        ]);
    }

    public function getSubheading(): ?string
    {
        if ($this->marktplaatsDemoContext() !== null) {
            return "Bekijk hoe onderhoud, kilometerstanden, facturen en foto's samen een duidelijke verkoophistorie vormen.";
        }

        return __('dashboard.subheading');
    }

    public function headerWidgets(Schema $schema): Schema
    {
        $marktplaatsDemoContext = $this->marktplaatsDemoContext();

        if ($marktplaatsDemoContext !== null) {
            return $schema->components([
                View::make('filament.pages.marktplaats-demo-dashboard')
                    ->viewData([
                        'registerUrl' => $marktplaatsDemoContext['register_url'],
                        'timelineUrl' => route('filament.admin.pages.tijdlijn'),
                    ]),
            ]);
        }

        $user = Filament::auth()->user();
        $hasFuelLogs = $user?->vehicles()->whereHas('fuelLogs')->exists();
        $isActivated = $user && DashboardOnboardingWidget::resolveProgressForUser($user)['is_complete'];

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

        $primaryWidget = $isActivated
            ? Livewire::make(DashboardActions::class)
            : Livewire::make(DashboardOnboardingWidget::class);

        return $schema->components([
            $primaryWidget,
            Livewire::make(PublicVehiclePagesWidget::class),
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

    /**
     * @return array{prospect_id:string, register_url:string}|null
     */
    private function marktplaatsDemoContext(): ?array
    {
        return app(OutreachDemoService::class)->marktplaats2026DemoContextForAuthenticatedUser();
    }
}
