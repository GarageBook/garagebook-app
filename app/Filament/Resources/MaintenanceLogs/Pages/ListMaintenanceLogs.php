<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\Concerns\HasPublicVehicleShareActions;
use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Models\Vehicle;
use App\Support\MaintenanceLogVehicleResolver;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListMaintenanceLogs extends ListRecords
{
    use HasPublicVehicleShareActions;

    protected static string $resource = MaintenanceLogResource::class;

    #[Url(as: 'vehicle_id')]
    public ?int $activeVehicleId = null;

    public function mount(): void
    {
        parent::mount();

        $this->activeVehicleId = $this->resolveActiveVehicleId($this->activeVehicleId);
    }

    public function updatedActiveVehicleId(): void
    {
        $this->activeVehicleId = $this->resolveActiveVehicleId($this->activeVehicleId);
        $this->cachedHeaderActions = [];
        $this->cachedActions = [];
        $this->cacheInteractsWithHeaderActions();
        $this->resetPage();
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.maintenance-logs.pages.vehicle-selector', [
            'actions' => $this->getCachedHeaderActions(),
            'activeVehicle' => $this->getActiveVehicle(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'vehicles' => $this->getUserVehicles(),
        ]);
    }

    public function getHeading(): string
    {
        return __('maintenance.heading');
    }

    public function getSubheading(): ?string
    {
        return __('maintenance.subheading');
    }

    protected function getHeaderActions(): array
    {
        $vehicle = $this->getActiveVehicle();

        if (! $vehicle) {
            return [];
        }

        return $this->getPublicVehicleShareActions($vehicle);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (! $this->activeVehicleId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('vehicle_id', $this->activeVehicleId);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Vehicle>
     */
    protected function getUserVehicles()
    {
        return Vehicle::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    protected function getActiveVehicle(): ?Vehicle
    {
        if (! $this->activeVehicleId) {
            return null;
        }

        return $this->getUserVehicles()->firstWhere('id', $this->activeVehicleId);
    }

    protected function resolveActiveVehicleId(?int $vehicleId): ?int
    {
        return app(MaintenanceLogVehicleResolver::class)->resolveForUser(auth()->user(), $vehicleId);
    }
}
