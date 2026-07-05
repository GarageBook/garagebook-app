<?php

namespace App\Services\Lifecycle;

use App\Models\User;

class EngagementScoreService
{
    public function __construct(private readonly VehicleHealthCompletenessService $vehicleHealth) {}

    public function score(User $user): int
    {
        $vehicles = $user->vehicles()->with(['maintenanceLogs', 'documents', 'fuelLogs'])->get();
        $score = 0;

        if ($vehicles->isNotEmpty()) {
            $score += 20;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $vehicle->maintenanceLogs->isNotEmpty())) {
            $score += 25;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->hasPhoto($vehicle))) {
            $score += 10;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $this->vehicleHealth->hasDocument($vehicle))) {
            $score += 15;
        }

        if ($vehicles->contains(fn ($vehicle): bool => (bool) $vehicle->is_public && filled($vehicle->public_slug))) {
            $score += 10;
        }

        if ($vehicles->contains(fn ($vehicle): bool => $vehicle->fuelLogs->isNotEmpty())) {
            $score += 10;
        }

        if ($vehicles->contains(fn ($vehicle): bool => (int) $vehicle->current_km > 0
            || filled($vehicle->year)
            || filled($vehicle->license_plate)
            || $vehicle->maintenanceLogs->contains(fn ($log): bool => (int) $log->km_reading > 0)
            || $vehicle->fuelLogs->contains(fn ($log): bool => (float) $log->odometer_km > 0))) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }
}
