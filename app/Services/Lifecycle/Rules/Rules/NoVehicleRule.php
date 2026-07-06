<?php

namespace App\Services\Lifecycle\Rules\Rules;

use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;

class NoVehicleRule implements LifecycleRule
{
    public function name(): string
    {
        return 'no_vehicle';
    }

    public function priority(): int
    {
        return 100;
    }

    public function cooldownDays(): int
    {
        return 2;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        if (! $user->vehicles()->exists()) {
            return LifecycleRuleResult::match($this->name(), 'User heeft nog geen voertuig.', $this->priority(), $this->cooldownDays());
        }

        return LifecycleRuleResult::miss($this->name(), 'User heeft al een voertuig.', $this->priority(), $this->cooldownDays());
    }
}
