<?php

namespace App\Services\Lifecycle\Rules\Rules;

use App\Models\User;
use App\Services\Lifecycle\Rules\LifecycleRule;
use App\Services\Lifecycle\Rules\LifecycleRuleResult;

class UploadDocumentRule implements LifecycleRule
{
    public function name(): string
    {
        return 'upload_document';
    }

    public function priority(): int
    {
        return 70;
    }

    public function cooldownDays(): int
    {
        return 7;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function evaluate(User $user): LifecycleRuleResult
    {
        if (! $user->vehicles()->whereHas('maintenanceLogs')->exists()) {
            return LifecycleRuleResult::miss($this->name(), 'Document is pas relevant na eerste onderhoud.', $this->priority(), $this->cooldownDays());
        }

        if (! $user->vehicles()->whereHas('documents')->exists()) {
            return LifecycleRuleResult::match($this->name(), 'User heeft onderhoud maar nog geen document.', $this->priority(), $this->cooldownDays());
        }

        return LifecycleRuleResult::miss($this->name(), 'User heeft al een document.', $this->priority(), $this->cooldownDays());
    }
}
