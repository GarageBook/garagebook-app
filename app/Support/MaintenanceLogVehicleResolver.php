<?php

namespace App\Support;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class MaintenanceLogVehicleResolver
{
    public function resolveForUser(?User $user, ?int $requestedVehicleId = null): ?int
    {
        if (! $user instanceof User) {
            return null;
        }

        $vehicles = $this->getUserVehicles($user);

        if ($vehicles->isEmpty()) {
            return null;
        }

        if ($requestedVehicleId && $vehicles->contains('id', $requestedVehicleId)) {
            return $requestedVehicleId;
        }

        $latestMaintenanceVehicleId = MaintenanceLog::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->value('vehicle_id');

        if ($latestMaintenanceVehicleId && $vehicles->contains('id', $latestMaintenanceVehicleId)) {
            return $latestMaintenanceVehicleId;
        }

        return $vehicles->first()?->id;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function getUserVehicles(User $user): Collection
    {
        return Vehicle::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->get();
    }
}
