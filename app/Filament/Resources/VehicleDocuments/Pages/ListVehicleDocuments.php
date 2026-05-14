<?php

namespace App\Filament\Resources\VehicleDocuments\Pages;

use App\Filament\Resources\VehicleDocuments\VehicleDocumentResource;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Support\Analytics;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListVehicleDocuments extends ListRecords
{
    protected static string $resource = VehicleDocumentResource::class;

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
        return view('filament.resources.vehicle-documents.pages.header', [
            'actions' => $this->getCachedHeaderActions(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'vehicles' => $this->getUserVehicles(),
            'activeVehicle' => $this->getActiveVehicle(),
            'hasDocuments' => $this->getActiveVehicle()?->documents->isNotEmpty() ?? false,
        ]);
    }

    public function getHeading(): string
    {
        return __('documents.heading');
    }

    public function getSubheading(): ?string
    {
        return __('documents.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('documents.actions.add_document'))
                ->extraAttributes(Analytics::clickTrackingAttributes('app_cta_clicked', [
                    'cta_name' => 'upload_document',
                    'location' => 'vehicle_documents_header',
                    'user_state' => Analytics::userState(auth()->user()),
                ]))
                ->url(fn (): string => static::getResource()::getUrl('create', [
                    'vehicle_id' => $this->activeVehicleId,
                ])),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with('vehicle');

        if (! $this->activeVehicleId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('vehicle_id', $this->activeVehicleId);
    }

    protected function getUserVehicles()
    {
        return Vehicle::query()
            ->where('user_id', auth()->id())
            ->withCount('documents')
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

        $latestDocumentVehicleId = VehicleDocument::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->value('vehicle_id');

        if ($latestDocumentVehicleId && $vehicles->contains('id', $latestDocumentVehicleId)) {
            return $latestDocumentVehicleId;
        }

        return $vehicles->first()->id;
    }
}
