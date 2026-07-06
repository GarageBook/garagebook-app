<?php

namespace App\Services\Lifecycle\Rules;

use App\Models\LifecycleRuleEvaluation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LifecycleRuleEngine
{
    public function __construct(private readonly LifecycleRuleRegistry $registry) {}

    /**
     * @return array{winner: ?LifecycleRuleResult, results: Collection<int, LifecycleRuleResult>}
     */
    public function evaluate(User $user, ?Carbon $evaluatedAt = null, bool $persist = true): array
    {
        $evaluatedAt ??= now();
        $results = collect();

        foreach ($this->registry->rules() as $rule) {
            if (! $rule->enabled()) {
                continue;
            }

            if ($this->isCoolingDown($user, $rule, $evaluatedAt)) {
                $results->push(LifecycleRuleResult::miss(
                    $rule->name(),
                    'Rule cooldown actief.',
                    $rule->priority(),
                    $rule->cooldownDays(),
                    ['cooldown_blocked' => true],
                ));

                continue;
            }

            $results->push($rule->evaluate($user));
        }

        $winner = $results
            ->filter(fn (LifecycleRuleResult $result): bool => $result->matched)
            ->sortByDesc(fn (LifecycleRuleResult $result): array => [$result->priority, $result->ruleName])
            ->first();

        if ($persist) {
            $this->recordEvaluations($user, $results, $winner, $evaluatedAt);
        }

        return [
            'winner' => $winner,
            'results' => $results,
        ];
    }

    private function isCoolingDown(User $user, LifecycleRule $rule, Carbon $evaluatedAt): bool
    {
        $latest = LifecycleRuleEvaluation::query()
            ->where('user_id', $user->id)
            ->where('rule_name', $rule->name())
            ->where('matched', true)
            ->latest('evaluated_at')
            ->latest('id')
            ->first();

        return $latest instanceof LifecycleRuleEvaluation
            && $latest->cooldown_until !== null
            && $latest->cooldown_until->gt($evaluatedAt);
    }

    /**
     * @param  Collection<int, LifecycleRuleResult>  $results
     */
    private function recordEvaluations(User $user, Collection $results, ?LifecycleRuleResult $winner, Carbon $evaluatedAt): void
    {
        // Shadow evaluations are historical by design; retention/snapshot compaction is a later lifecycle sprint.
        foreach ($results as $result) {
            LifecycleRuleEvaluation::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'rule_name' => $result->ruleName,
                    'evaluated_at' => $evaluatedAt,
                ],
                [
                    'matched' => $winner !== null && $result->ruleName === $winner->ruleName,
                    'reason' => $result->reason,
                    'cooldown_until' => $winner !== null && $result->ruleName === $winner->ruleName && $result->cooldownDays > 0
                        ? $evaluatedAt->copy()->addDays($result->cooldownDays)
                        : null,
                    'metadata' => [
                        ...$result->metadata,
                        'priority' => $result->priority,
                        'shadow_mode' => true,
                        'raw_match' => $result->matched,
                    ],
                ],
            );
        }
    }
}
