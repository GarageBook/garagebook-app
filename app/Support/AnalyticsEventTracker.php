<?php

namespace App\Support;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AnalyticsEventTracker
{
    public const SESSION_KEY = 'garagebook.analytics_events';

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
        $this->queue('vehicle_created', [
            ...$this->vehicleTypeParams($vehicle),
            'source' => 'app',
        ]);
    }

    public function queueMaintenanceLogCreated(MaintenanceLog $maintenanceLog): void
    {
        $this->queue('maintenance_log_created', [
            ...$this->vehicleTypeParams($maintenanceLog->vehicle),
            'has_cost' => filled($maintenanceLog->cost),
            'has_attachment' => $maintenanceLog->attachments !== [],
            'source' => 'app',
        ]);
    }

    public function queueFuelEntryCreated(FuelLog $fuelLog): void
    {
        $this->queue('fuel_entry_created', [
            ...$this->vehicleTypeParams($fuelLog->vehicle),
            'source' => 'app',
        ]);
    }

    public function queueDocumentUploaded(VehicleDocument $vehicleDocument): void
    {
        $this->queue('document_uploaded', [
            ...$this->documentTypeParams($vehicleDocument),
            'source' => 'app',
        ]);
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
        return array_filter(
            $params,
            fn (mixed $value): bool => is_string($value) || is_int($value) || is_float($value) || is_bool($value)
        );
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
}
