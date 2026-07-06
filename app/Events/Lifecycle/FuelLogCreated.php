<?php

namespace App\Events\Lifecycle;

use App\Models\FuelLog;
use App\Models\User;

class FuelLogCreated
{
    public function __construct(public readonly FuelLog $fuelLog) {}

    public function user(): ?User
    {
        return $this->fuelLog->vehicle()->first()?->user()->first();
    }
}
