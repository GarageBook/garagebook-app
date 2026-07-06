<?php

namespace App\Events\Lifecycle;

use App\Models\User;
use Illuminate\Support\Carbon;

class LifecycleStateChanged
{
    public function __construct(
        public readonly User $user,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly Carbon $changedAt,
    ) {}
}
