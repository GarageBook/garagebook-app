<?php

namespace App\Services\Lifecycle;

use App\Enums\LifecycleState;
use App\Models\User;

class LifecycleStateService
{
    public function __construct(private readonly VehicleHealthCompletenessService $vehicleHealth) {}

    public function determine(User $user): LifecycleState
    {
        $vehicles = $user->vehicles()->with(['maintenanceLogs', 'documents', 'fuelLogs'])->get();

        if ($vehicles->isEmpty()) {
            return LifecycleState::REGISTERED;
        }

        if ($this->isHealthyGarage($user)) {
            return LifecycleState::HEALTHY_GARAGE;
        }

        if ($vehicles->contains(fn ($vehicle): bool => (bool) $vehicle->is_public && filled($vehicle->public_slug))) {
            return LifecycleState::PUBLIC_GARAGE_ENABLED;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->hasDocument($vehicle))) {
            return LifecycleState::DOCUMENTS_ADDED;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->isProfileComplete($vehicle))) {
            return LifecycleState::VEHICLE_PROFILE_COMPLETE;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $vehicle->maintenanceLogs->isNotEmpty())) {
            return LifecycleState::FIRST_MAINTENANCE_LOGGED;
        }

        return LifecycleState::VEHICLE_ADDED;
    }

    public function isHealthyGarage(User $user): bool
    {
        $vehicles = $user->vehicles()->with(['maintenanceLogs', 'documents'])->get();

        return $vehicles->isNotEmpty()
            && $vehicles->contains(fn ($vehicle): bool => (bool) $vehicle->is_public && filled($vehicle->public_slug))
            && $vehicles->every(fn ($vehicle): bool => $this->vehicleHealth->hasMaintenance($vehicle)
                && $this->vehicleHealth->hasPhoto($vehicle)
                && $this->vehicleHealth->hasDocument($vehicle)
                && $this->vehicleHealth->hasRecentActivity($vehicle));
    }
}
