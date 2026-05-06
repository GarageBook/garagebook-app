<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Illuminate\Support\Str;

class ListMaintenanceLogs extends ListRecords
{
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
        $this->resetPage();
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.maintenance-logs.pages.vehicle-selector', [
            'actions' => $this->getCachedHeaderActions(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'vehicles' => $this->getUserVehicles(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $vehicle = $this->getActiveVehicle();

        if (! $vehicle) {
            return [];
        }

        $shareUrl = url('/share/' .
            Str::slug(auth()->user()->name) . '/' .
            Str::slug($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model));

        $pdfUrl = url('/maintenance/pdf?vehicle_id=' . $vehicle->id);

        return [
            Action::make('openSharePage')
                ->label('Open als externe pagina')
                ->url($shareUrl)
                ->openUrlInNewTab()
                ->color('warning')
                ->outlined(),

            Action::make('copyUrl')
                ->label('Kopieer URL')
                ->extraAttributes([
                    'onclick' => "navigator.clipboard.writeText('{$shareUrl}')",
                ])
                ->color('warning')
                ->outlined(),

            Action::make('exportPdf')
                ->label('Exporteer PDF')
                ->url($pdfUrl)
                ->openUrlInNewTab()
                ->color('warning')
                ->outlined(),
        ];
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
        $vehicles = $this->getUserVehicles();

        if ($vehicles->isEmpty()) {
            return null;
        }

        if ($vehicleId && $vehicles->contains('id', $vehicleId)) {
            return $vehicleId;
        }

        return $vehicles->first()->id;
    }
}
