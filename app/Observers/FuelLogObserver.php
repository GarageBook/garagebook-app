<?php

namespace App\Observers;

use App\Events\Lifecycle\FuelLogCreated;
use App\Models\FuelLog;

class FuelLogObserver
{
    public function created(FuelLog $fuelLog): void
    {
        event(new FuelLogCreated($fuelLog));
    }
}
