<?php

namespace App\Support;

use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\TripLog;
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
        $this->queue('registration_started', [
            'page_location' => request()->fullUrl(),
            'page_path' => request()->getPathInfo(),
            'utm_source' => Arr::get($attribution, 'utm_source'),
            'utm_medium' => Arr::get($attribution, 'utm_medium'),
            'utm_campaign' => Arr::get($attribution, 'utm_campaign'),
            ...$this->outreachDemoParams($attribution),
        ]);
    }

    public function queueSignUp(string $method = 'email', ?string $registrationSource = null, ?array $attribution = null): void
    {
        $params = [
            'method' => $method,
        ];

        if (filled($registrationSource)) {
            $params['registration_source'] = $registrationSource;
        }

        $this->queue('registration_completed', [
            ...$params,
            ...$this->outreachDemoParams($attribution),
        ]);
    }

    public function queueOutreachDemoVehicleCreateBlocked(int $demoUserId, ?int $outreachProspectId = null): void
    {
        $this->queue('outreach_demo_vehicle_create_blocked', array_filter([
            'demo_user_id' => $demoUserId,
            'outreach_prospect_id' => $outreachProspectId,
            'intended' => 'vehicle_create',
        ], fn (mixed $value): bool => $value !== null));
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
            'source' => 'filament',
            ...$this->vehicleTypeParams($vehicle),
        ]);
    }

    public function queueMaintenanceLogCreated(MaintenanceLog $maintenanceLog): void
    {
        $this->queue('maintenance_log_created', [
            'source' => 'filament',
        ]);
    }

    public function queueOnboardingWidgetViewed(mixed $nextStep, int $completedSteps, int $totalSteps): void
    {
        if (is_array($nextStep)) {
            $nextStep = (string) ($nextStep['key'] ?? $nextStep['step'] ?? 'vehicle');
        }

        if (! is_string($nextStep) || $nextStep === '') {
            $nextStep = 'vehicle';
        }

        $this->queue('onboarding_widget_viewed', [
            'next_step' => $nextStep,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
        ]);
    }

    public function queueOnboardingCompleted(User $user, MaintenanceLog $maintenanceLog): void
    {
        $this->queue('onboarding_completed', [
            'source' => 'filament',
            'vehicle_id_hash' => Analytics::anonymizeIdentifier('vehicle', $maintenanceLog->vehicle_id),
            'user_state' => Analytics::userState($user),
        ]);
    }

    public function queueReminderCreated(Vehicle $vehicle, string $type): void
    {
        $this->queue('reminder_created', [
            'source' => 'filament',
            'type' => $type,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function queuePublicVehiclePageViewed(Vehicle $vehicle): void
    {
        $this->queue('public_vehicle_page_viewed', [
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function queueExportClicked(string $type): void
    {
        $this->queue('export_clicked', [
            'type' => $type,
        ]);
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
        $this->queue('document_uploaded', [
            'source' => 'filament',
        ]);
    }

    public function queueTripLogCreated(TripLog $tripLog): void
    {
        $this->queue('trip_created', [
            'source' => 'filament',
        ]);
    }

    public function queueLifecycleEmailSent(?User $user, string $emailKey): void
    {
        $params = [
            'email_key' => $emailKey,
            'user_state' => Analytics::userState($user),
        ];

        $this->queue('lifecycle_email_sent', $params);
        logger()->info('lifecycle_email_sent', array_filter($params, fn (mixed $value): bool => $value !== null));
    }

    public function queueLifecycleEmailClicked(string $emailKey): void
    {
        $this->queue('lifecycle_email_clicked', [
            'email_key' => $emailKey,
        ]);
    }

    public function queuePublicVehicleDashboardWidgetViewed(int $publicVehicleCount): void
    {
        $this->queue('public_vehicle_dashboard_widget_viewed', [
            'public_vehicle_count' => $publicVehicleCount,
        ]);
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

    protected function outreachDemoParams(?array $attribution): array
    {
        if (! is_array($attribution)) {
            return [];
        }

        return array_filter([
            'source' => Arr::get($attribution, 'source'),
            'demo_user_id' => is_numeric(Arr::get($attribution, 'demo_user_id')) ? (int) Arr::get($attribution, 'demo_user_id') : null,
            'outreach_prospect_id' => is_numeric(Arr::get($attribution, 'outreach_prospect_id')) ? (int) Arr::get($attribution, 'outreach_prospect_id') : null,
            'intended' => Arr::get($attribution, 'intended'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
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
}
