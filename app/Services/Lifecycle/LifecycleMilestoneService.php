<?php

namespace App\Services\Lifecycle;

use App\Enums\LifecycleMilestone;
use App\Models\User;

class LifecycleMilestoneService
{
    public function __construct(private readonly VehicleHealthCompletenessService $vehicleHealth) {}

    /**
     * @return array<int, LifecycleMilestone>
     */
    public function achieved(User $user): array
    {
        $vehicles = $user->vehicles()->with(['maintenanceLogs', 'documents', 'fuelLogs'])->get();
        $maintenanceCount = $vehicles->sum(fn ($vehicle): int => $vehicle->maintenanceLogs->count());

        $milestones = [];

        if ($vehicles->isNotEmpty()) {
            $milestones[] = LifecycleMilestone::FIRST_VEHICLE;
        }

        if ($maintenanceCount > 0) {
            $milestones[] = LifecycleMilestone::FIRST_MAINTENANCE;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->hasPhoto($vehicle))) {
            $milestones[] = LifecycleMilestone::FIRST_PHOTO;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->hasDocument($vehicle))) {
            $milestones[] = LifecycleMilestone::FIRST_DOCUMENT;
        }

        if ($vehicles->contains(fn ($vehicle): bool => (bool) $vehicle->is_public && filled($vehicle->public_slug))) {
            $milestones[] = LifecycleMilestone::PUBLIC_GARAGE;
        }

        if ($maintenanceCount >= 5) {
            $milestones[] = LifecycleMilestone::FIVE_MAINTENANCE_LOGS;
        }

        if ($maintenanceCount >= 10) {
            $milestones[] = LifecycleMilestone::TEN_MAINTENANCE_LOGS;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $vehicle->maintenanceLogs->contains(fn ($log): bool => (float) $log->cost > 0))) {
            $milestones[] = LifecycleMilestone::FIRST_SERVICE_COST;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $vehicle->fuelLogs->isNotEmpty())) {
            $milestones[] = LifecycleMilestone::FIRST_FUEL_LOG;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->isProfileComplete($vehicle))) {
            $milestones[] = LifecycleMilestone::COMPLETE_PROFILE;
        }

        return $milestones;
    }
}
