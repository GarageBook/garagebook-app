<?php

namespace App\Observers;

use App\Events\Lifecycle\GaragePublished;
use App\Events\Lifecycle\VehicleCreated;
use App\Models\Vehicle;

class VehicleObserver
{
    public function created(Vehicle $vehicle): void
    {
        event(new VehicleCreated($vehicle));
    }

    public function updated(Vehicle $vehicle): void
    {
        if (! $this->becamePublic($vehicle)) {
            return;
        }

        event(new GaragePublished($vehicle));
    }

    private function becamePublic(Vehicle $vehicle): bool
    {
        if (! (bool) $vehicle->is_public || blank($vehicle->public_slug)) {
            return false;
        }

        return $vehicle->wasChanged('is_public') || $vehicle->wasChanged('public_slug');
    }
}
