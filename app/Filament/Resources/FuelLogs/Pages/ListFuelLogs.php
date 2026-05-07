<?php

namespace App\Filament\Resources\FuelLogs\Pages;

use App\Filament\Resources\FuelLogs\FuelLogResource;
use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Services\DistanceUnitService;
use App\Services\FuelConsumptionService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListFuelLogs extends ListRecords
{
    protected static string $resource = FuelLogResource::class;

    #[Url(as: 'vehicle_id')]
    public ?int $activeVehicleId = null;

    public string $consumptionUnit = FuelConsumptionService::UNIT_L_PER_100_KM;

    public function mount(): void
    {
        parent::mount();

        $this->activeVehicleId = $this->resolveActiveVehicleId($this->activeVehicleId);
        $this->consumptionUnit = app(FuelConsumptionService::class)->normalizeUnit(auth()->user()?->consumption_unit);
    }

    public function updatedActiveVehicleId(): void
    {
        $this->activeVehicleId = $this->resolveActiveVehicleId($this->activeVehicleId);
        $this->resetPage();
    }

    public function setConsumptionUnit(string $unit): void
    {
        $service = app(FuelConsumptionService::class);

        $this->consumptionUnit = $service->normalizeUnit($unit);
        auth()->user()?->forceFill([
            'consumption_unit' => $this->consumptionUnit,
        ])->save();
    }

    public function getHeader(): ?View
    {
        $vehicle = $this->getActiveVehicle();
        $summary = $vehicle ? app(FuelConsumptionService::class)->getVehicleSummary($vehicle, $this->consumptionUnit) : null;
        $distanceUnit = app(DistanceUnitService::class);

        if ($summary && $vehicle) {
            $summary['distance_label'] = $distanceUnit->formatFromKilometers(
                $summary['distance_km'],
                $vehicle->distance_unit,
                1
            );
        }

        return view('filament.resources.fuel-logs.pages.header', [
            'actions' => $this->getCachedHeaderActions(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'vehicles' => $this->getUserVehicles(),
            'activeVehicle' => $vehicle,
            'hasFuelLogs' => $vehicle && $vehicle->fuelLogs->isNotEmpty(),
            'summary' => $summary,
            'consumptionUnit' => $this->consumptionUnit,
            'supportedUnits' => app(FuelConsumptionService::class)->getSupportedUnits(),
        ]);
    }

    public function getHeading(): string
    {
        return 'Verbruik';
    }

    public function getSubheading(): ?string
    {
        return 'Houd tankbeurten per voertuig bij en bekijk direct het gemiddelde verbruik.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Verbruik toevoegen')
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
            ->withSum('fuelLogs', 'distance_km')
            ->withSum('fuelLogs', 'fuel_liters')
            ->with(['fuelLogs' => fn ($query) => $query
                ->select('id', 'vehicle_id', 'fuel_liters', 'price_per_liter')
                ->latest('fuel_date')
                ->latest('id')])
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

        $latestFuelVehicleId = FuelLog::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->orderByDesc('fuel_date')
            ->orderByDesc('id')
            ->value('vehicle_id');

        if ($latestFuelVehicleId && $vehicles->contains('id', $latestFuelVehicleId)) {
            return $latestFuelVehicleId;
        }

        return $vehicles->first()->id;
    }
}
