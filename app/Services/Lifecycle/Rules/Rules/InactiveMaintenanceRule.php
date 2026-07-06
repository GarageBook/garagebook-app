<?php

namespace App\Services\Lifecycle\Rules\Rules;

use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;

class InactiveMaintenanceRule implements LifecycleRule
{
    public function name(): string
    {
        return 'inactive_maintenance';
    }

    public function priority(): int
    {
        return 50;
    }

    public function cooldownDays(): int
    {
        return 30;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        $latestMaintenanceAt = $user->vehicles()
            ->join('maintenance_logs', 'maintenance_logs.vehicle_id', '=', 'vehicles.id')
            ->max('maintenance_logs.created_at');

        if ($latestMaintenanceAt === null) {
            return LifecycleRuleResult::miss($this->name(), 'User heeft nog geen onderhoudslog.', $this->priority(), $this->cooldownDays());
        }

        if (now()->parse($latestMaintenanceAt)->lte(now()->subDays(90))) {
            return LifecycleRuleResult::match($this->name(), 'Laatste onderhoudslog is ouder dan 90 dagen.', $this->priority(), $this->cooldownDays());
        }

        return LifecycleRuleResult::miss($this->name(), 'Recent onderhoud aanwezig.', $this->priority(), $this->cooldownDays());
    }
}
