<?php

namespace App\Policies;

use App\Models\TripLog;
use App\Models\User;

class TripLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TripLog $tripLog): bool
    {
        return (int) $tripLog->vehicle->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TripLog $tripLog): bool
    {
        return (int) $tripLog->vehicle->user_id === $user->id;
    }

    public function delete(User $user, TripLog $tripLog): bool
    {
        return (int) $tripLog->vehicle->user_id === $user->id;
    }
}
