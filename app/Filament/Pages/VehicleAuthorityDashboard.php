<?php

namespace App\Filament\Pages;

use App\Models\VehicleAuthorityIndex;
use App\Services\VehicleAuthorityIndexService;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class VehicleAuthorityDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?int $navigationSort = 193;

    protected string $view = 'filament.pages.vehicle-authority-dashboard';

    public array $stats = [];

    /** @var Collection<int, VehicleAuthorityIndex> */
    public Collection $topModels;

    /** @var Collection<int, VehicleAuthorityIndex> */
    public Collection $newestModels;

    /** @var Collection<int, VehicleAuthorityIndex> */
    public Collection $filteredModels;

    public string $filterBrand = '';

    public bool $filterIndexableOnly = true;

    public int $filterMinPublicVehicles = 0;

    public function mount(VehicleAuthorityIndexService $indexService): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->filterBrand = (string) request('brand', '');
        $this->filterIndexableOnly = request()->boolean('indexable', true);
        $this->filterMinPublicVehicles = request()->integer('min_vehicles', 0);

        $this->stats = $indexService->stats();
        $this->topModels = $indexService->topModels(20);
        $this->newestModels = $indexService->newestModels(10);

        $filters = [];

        if ($this->filterBrand !== '') {
            $filters['brand'] = $this->filterBrand;
        }

        if ($this->filterMinPublicVehicles > 0) {
            $filters['min_public_vehicles'] = $this->filterMinPublicVehicles;
        }

        $this->filteredModels = $indexService->indexableModels($filters);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.navigation.management');
    }

    public static function getNavigationLabel(): string
    {
        return 'Vehicle Authority';
    }

    public function getHeading(): string
    {
        return 'Vehicle Authority Dashboard';
    }

    public function getTitle(): string
    {
        return 'Vehicle Authority Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Overzicht van geïndexeerde voertuigmodellen. Voer garagebook:vehicle-authority:sync uit om bij te werken.';
    }
}
