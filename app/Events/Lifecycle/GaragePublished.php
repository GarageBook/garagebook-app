<?php

namespace App\Events\Lifecycle;

use App\Models\User;
use App\Models\Vehicle;

class GaragePublished
{
    public function __construct(public readonly Vehicle $vehicle) {}

    public function user(): ?User
    {
        return $this->vehicle->user()->first();
    }
}
