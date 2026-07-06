<?php

namespace App\Services\Lifecycle\Rules\Rules;

use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;
use App\Services\Lifecycle\VehicleHealthCompletenessService;

class VehiclePhotoReminderRule implements LifecycleRule
{
    public function __construct(private readonly VehicleHealthCompletenessService $vehicleHealth) {}

    public function name(): string
    {
        return 'vehicle_photo_reminder';
    }

    public function priority(): int
    {
        return 60;
    }

    public function cooldownDays(): int
    {
        return 10;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        $vehicles = $user->vehicles()->get();

        if ($vehicles->isEmpty()) {
            return LifecycleRuleResult::miss($this->name(), 'User heeft nog geen voertuig.', $this->priority(), $this->cooldownDays());
        }

        if ($vehicles->contains(fn ($vehicle): bool => ! $this->vehicleHealth->hasPhoto($vehicle))) {
            return LifecycleRuleResult::match($this->name(), 'Minimaal een voertuig mist nog een foto.', $this->priority(), $this->cooldownDays());
        }

        return LifecycleRuleResult::miss($this->name(), 'Voertuigen hebben al een foto.', $this->priority(), $this->cooldownDays());
    }
}
