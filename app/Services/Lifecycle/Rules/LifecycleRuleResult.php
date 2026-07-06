<?php

namespace App\Services\Lifecycle\Rules;

class LifecycleRuleResult
{
    public function __construct(
        public readonly string $ruleName,
        public readonly bool $matched,
        public readonly string $reason,
        public readonly int $priority,
        public readonly int $cooldownDays,
        public readonly array $metadata = [],
    ) {}

    public static function match(string $ruleName, string $reason, int $priority, int $cooldownDays, array $metadata = []): self
    {
        return new self($ruleName, true, $reason, $priority, $cooldownDays, $metadata);
    }

    public static function miss(string $ruleName, string $reason, int $priority, int $cooldownDays, array $metadata = []): self
    {
        return new self($ruleName, false, $reason, $priority, $cooldownDays, $metadata);
    }
}
