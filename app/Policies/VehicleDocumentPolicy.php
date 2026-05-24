<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleDocument;

class VehicleDocumentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleDocument $vehicleDocument): bool
    {
        return (int) $vehicleDocument->vehicle->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VehicleDocument $vehicleDocument): bool
    {
        return (int) $vehicleDocument->vehicle->user_id === $user->id;
    }

    public function delete(User $user, VehicleDocument $vehicleDocument): bool
    {
        return (int) $vehicleDocument->vehicle->user_id === $user->id;
    }
}
