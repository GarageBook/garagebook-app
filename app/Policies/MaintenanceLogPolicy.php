<?php

namespace App\Policies;

use App\Models\MaintenanceLog;
use App\Models\User;

class MaintenanceLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenanceLog $maintenanceLog): bool
    {
        return (int) $maintenanceLog->vehicle->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MaintenanceLog $maintenanceLog): bool
    {
        return (int) $maintenanceLog->vehicle->user_id === $user->id;
    }

    public function delete(User $user, MaintenanceLog $maintenanceLog): bool
    {
        return (int) $maintenanceLog->vehicle->user_id === $user->id;
    }
}
