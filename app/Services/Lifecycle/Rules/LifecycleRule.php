<?php

namespace App\Services\Lifecycle\Rules;

use App\Models\User;

interface LifecycleRule
{
    public function name(): string;

    public function priority(): int;

    public function cooldownDays(): int;

    public function enabled(): bool;

    public function evaluate(User $user): LifecycleRuleResult;
}
