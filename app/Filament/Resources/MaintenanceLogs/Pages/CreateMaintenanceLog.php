<?php

namespace App\Filament\Resources\MaintenanceLogs\Pages;

use App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource;
use App\Jobs\OptimizeMaintenanceLogMedia;
use App\Services\DistanceUnitService;
use App\Services\LifecycleEmailService;
use App\Support\AnalyticsEventTracker;
use App\Support\MaintenanceLogVehicleResolver;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceLog extends CreateRecord
{
    protected static string $resource = MaintenanceLogResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['vehicle_id'] ?? null)) {
            $data['vehicle_id'] = app(MaintenanceLogVehicleResolver::class)->resolveForUser(
                auth()->user(),
                request()->integer('vehicle_id') ?: null,
            );
        }

        $service = app(DistanceUnitService::class);
        $unit = $service->persistVehicleUnit(
            isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            $data['distance_unit'] ?? null
        );

        $data['km_reading'] = (int) round($service->toKilometers($data['km_reading'] ?? null, $unit, 0) ?? 0);
        $data['interval_km'] = $service->toKilometers($data['interval_km'] ?? null, $unit, 0);
        unset($data['distance_unit']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $tracker = app(AnalyticsEventTracker::class);
        $tracker->queueMaintenanceLogCreated($this->record);

        $user = auth()->user();
        $maintenanceCount = $user?->vehicles()->withCount('maintenanceLogs')->get()->sum('maintenance_logs_count') ?? 0;

        if ($user && $maintenanceCount === 1) {
            $tracker->queueOnboardingCompleted($user, $this->record);
            app(LifecycleEmailService::class)->markGoalCompletedForFirstMaintenanceLog($user);
        }

        OptimizeMaintenanceLogMedia::dispatch($this->record->getKey());
    }

    protected function getRedirectUrl(): string
    {
        return MaintenanceLogResource::getUrl('index');
    }

    public function getHeading(): string
    {
        if (request()->query('onboarding') === '1') {
            return 'Voeg je eerste onderhoud toe';
        }

        return parent::getHeading();
    }

    public function getSubheading(): ?string
    {
        if (request()->query('onboarding') === '1') {
            return 'Gefeliciteerd! Je voertuig is toegevoegd. Leg nu je laatste onderhoud vast om je historie te starten en de waarde van je motor te borgen.';
        }

        return parent::getSubheading();
    }
}
