<?php

namespace App\Services\Lifecycle\Rules\Rules;

use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;

class FirstMaintenanceRule implements LifecycleRule
{
    public function name(): string
    {
        return 'first_maintenance';
    }

    public function priority(): int
    {
        return 90;
    }

    public function cooldownDays(): int
    {
        return 3;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        if (! $user->vehicles()->exists()) {
            return LifecycleRuleResult::miss($this->name(), 'User heeft nog geen voertuig.', $this->priority(), $this->cooldownDays());
        }

        if (! $user->vehicles()->whereHas('maintenanceLogs')->exists()) {
            return LifecycleRuleResult::match($this->name(), 'User heeft wel een voertuig maar nog geen onderhoudslog.', $this->priority(), $this->cooldownDays());
        }

        return LifecycleRuleResult::miss($this->name(), 'User heeft al onderhoud vastgelegd.', $this->priority(), $this->cooldownDays());
    }
}
