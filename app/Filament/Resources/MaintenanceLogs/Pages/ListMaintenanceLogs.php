<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use App\Support\Analytics;
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
        $this->cachedHeaderActions = [];
        $this->cachedActions = [];
        $this->cacheInteractsWithHeaderActions();
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
                ->label(__('maintenance.actions.open_external_page'))
                ->url($shareUrl)
                ->openUrlInNewTab()
                ->extraAttributes(Analytics::clickTrackingAttributes('public_share_created', [
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                    'source' => 'share',
                ]))
                ->color('warning')
                ->outlined(),

            Action::make('copyUrl')
                ->label(__('maintenance.actions.copy_url'))
                ->extraAttributes([
                    'onclick' => "navigator.clipboard.writeText('{$shareUrl}')",
                    ...Analytics::clickTrackingAttributes('public_share_created', [
                        'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                        'source' => 'share',
                    ]),
                ])
                ->color('warning')
                ->outlined(),

            Action::make('exportPdf')
                ->label(__('maintenance.actions.export_pdf'))
                ->url($pdfUrl)
                ->openUrlInNewTab()
                ->extraAttributes(Analytics::clickTrackingAttributes('public_share_created', [
                    'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $vehicle->id),
                    'source' => 'export',
                ]))
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

        $latestMaintenanceVehicleId = MaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->value('vehicle_id');

        if ($latestMaintenanceVehicleId && $vehicles->contains('id', $latestMaintenanceVehicleId)) {
            return $latestMaintenanceVehicleId;
        }

        return $vehicles->first()->id;
    }
}
