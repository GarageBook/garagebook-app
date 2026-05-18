<?php

namespace App\Support;

use App\Models\TripLog;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AnalyticsEventTracker
{
    public const SESSION_KEY = 'garagebook.analytics_events';

    public function queueRegisterStart(?array $attribution = null): void
    {
        $this->queue('register_start', [
            'page_location' => request()->fullUrl(),
            'page_path' => request()->getPathInfo(),
            'utm_source' => Arr::get($attribution, 'utm_source'),
            'utm_medium' => Arr::get($attribution, 'utm_medium'),
            'utm_campaign' => Arr::get($attribution, 'utm_campaign'),
        ]);
    }

    public function queueSignUp(string $method = 'email'): void
    {
        $this->queue('sign_up', [
            'method' => $method,
        ]);
    }

    public function queueLogin(string $method = 'email'): void
    {
        $this->queue('login', [
            'method' => $method,
        ]);
    }

    public function queueVehicleCreated(Vehicle $vehicle): void
    {
        $vehicleCount = Vehicle::query()
            ->where('user_id', $vehicle->user_id)
            ->count();

        $this->queue('vehicle_created', [
            ...$this->vehicleTypeParams($vehicle),
            'is_first_vehicle' => $vehicleCount === 1,
        ]);
    }

    public function queueMaintenanceLogCreated(MaintenanceLog $maintenanceLog): void
    {
        $maintenanceLogCount = MaintenanceLog::query()
            ->where('vehicle_id', $maintenanceLog->vehicle_id)
            ->count();

        $this->queue('maintenance_log_created', $this->context(
            appSection: 'maintenance',
            extra: [
                'is_first_maintenance_log' => $maintenanceLogCount === 1,
                'has_attachments' => $this->hasMaintenanceAttachments($maintenanceLog),
                'cost_entered' => filled($maintenanceLog->cost),
            ],
        ));
    }

    public function queueFuelEntryCreated(FuelLog $fuelLog): void
    {
        $this->queue('fuel_log_created', $this->context(
            appSection: 'fuel',
            extra: [
                'unit' => $this->distanceUnit($fuelLog->vehicle),
                'calculated_consumption_available' => (float) $fuelLog->distance_km > 0 && (float) $fuelLog->fuel_liters > 0,
            ],
        ));
    }

    public function queueDocumentUploaded(VehicleDocument $vehicleDocument): void
    {
        $this->queue('document_uploaded', $this->context(
            appSection: 'documents',
            extra: [
                ...$this->documentTypeParams($vehicleDocument),
                'file_count' => filled($vehicleDocument->file_path) ? 1 : 0,
            ],
        ));
    }

    public function queueTripLogCreated(TripLog $tripLog): void
    {
        $this->queue('trip_log_created', $this->context(
            appSection: 'trips',
            extra: [
                ...$this->vehicleTypeParams($tripLog->vehicle),
            ],
        ));
    }

    public function queueDashboardViewed(User $user): void
    {
        $vehicles = Vehicle::query()
            ->where('user_id', $user->getKey())
            ->withCount(['maintenanceLogs', 'documents', 'fuelLogs'])
            ->get();

        $this->queue('dashboard_viewed', $this->context(
            appSection: 'dashboard',
            extra: [
                'vehicle_count' => $vehicles->count(),
                'maintenance_log_count' => (int) $vehicles->sum('maintenance_logs_count'),
                'document_count' => (int) $vehicles->sum('documents_count'),
                'fuel_log_count' => (int) $vehicles->sum('fuel_logs_count'),
            ],
        ));
    }

    public function consume(): array
    {
        if (! app()->bound('session')) {
            return [];
        }

        $events = session()->pull(self::SESSION_KEY, []);

        return is_array($events) ? $events : [];
    }

    public function queue(string $eventName, array $params = []): void
    {
        if (! app()->bound('session')) {
            return;
        }

        $events = session()->get(self::SESSION_KEY, []);
        $event = [
            'name' => $eventName,
            'params' => $this->sanitizeParams($params),
        ];

        if (($events[array_key_last($events)] ?? null) === $event) {
            session()->flash(self::SESSION_KEY, $events);

            return;
        }

        $events[] = $event;

        session()->flash(self::SESSION_KEY, $events);
    }

    protected function sanitizeParams(array $params): array
    {
        return Analytics::sanitizeParams($params);
    }

    protected function context(string $appSection = '', array $extra = []): array
    {
        return [
            ...array_filter([
                'app_section' => $appSection !== '' ? $appSection : null,
            ], fn (mixed $value): bool => $value !== null),
            ...$extra,
        ];
    }

    protected function vehicleTypeParams(?Model $vehicle): array
    {
        if (! $vehicle instanceof Model) {
            return [];
        }

        if (! Schema::hasColumn($vehicle->getTable(), 'vehicle_type')) {
            return [];
        }

        $vehicleType = $vehicle->getAttribute('vehicle_type');

        if (! filled($vehicleType)) {
            return [];
        }

        return [
            'vehicle_type' => (string) $vehicleType,
        ];
    }

    protected function documentTypeParams(VehicleDocument $vehicleDocument): array
    {
        if (! filled($vehicleDocument->document_type)) {
            return [];
        }

        return [
            'document_type' => (string) $vehicleDocument->document_type,
        ];
    }

    protected function distanceUnit(?Model $vehicle): ?string
    {
        if (! $vehicle instanceof Model) {
            return null;
        }

        $unit = $vehicle->getAttribute('distance_unit');

        if (! is_string($unit) || $unit === '') {
            return null;
        }

        return $unit === 'mi' ? 'mi' : 'km';
    }

    protected function hasMaintenanceAttachments(MaintenanceLog $maintenanceLog): bool
    {
        return $maintenanceLog->attachments !== []
            || $maintenanceLog->media_attachments !== []
            || $maintenanceLog->file_attachments !== [];
    }
}
