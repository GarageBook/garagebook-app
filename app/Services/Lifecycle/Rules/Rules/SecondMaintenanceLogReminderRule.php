<?php

namespace App\Services\Lifecycle\Rules\Rules;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;

class SecondMaintenanceLogReminderRule implements LifecycleRule
{
    public function name(): string
    {
        return 'second_maintenance_log_reminder';
    }

    public function priority(): int
    {
        return 80;
    }

    public function cooldownDays(): int
    {
        return 14;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        if (! $user->isCoreFunnelUser()) {
            return LifecycleRuleResult::miss($this->name(), 'User valt buiten de core funnel.', $this->priority(), $this->cooldownDays());
        }

        if (! $user->vehicles()->exists()) {
            return LifecycleRuleResult::miss($this->name(), 'User heeft nog geen voertuig.', $this->priority(), $this->cooldownDays());
        }

        $logs = MaintenanceLog::query()
            ->whereHas('vehicle', fn ($query) => $query->where('user_id', $user->getKey()))
            ->oldest('created_at')
            ->get(['id', 'created_at']);

        if ($logs->count() !== 1) {
            return LifecycleRuleResult::miss($this->name(), 'User heeft niet exact een onderhoudslog.', $this->priority(), $this->cooldownDays(), [
                'maintenance_logs_count' => $logs->count(),
            ]);
        }

        $firstLog = $logs->first();

        if (! $firstLog?->created_at || $firstLog->created_at->gt(now()->subDays(3))) {
            return LifecycleRuleResult::miss($this->name(), 'Eerste onderhoudslog is recenter dan 3 dagen.', $this->priority(), $this->cooldownDays());
        }

        return LifecycleRuleResult::match($this->name(), 'User heeft exact een onderhoudslog ouder dan 3 dagen.', $this->priority(), $this->cooldownDays(), [
            'first_maintenance_log_id' => $firstLog->getKey(),
        ]);
    }
}
