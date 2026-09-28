<?php

namespace App\Services\Growth;

class ActivationTrendService
{
    /**
     * @param  list<array<string, mixed>>  $cohorts
     * @return list<array<string, mixed>>
     */
    public function compare(array $cohorts): array
    {
        $eligible = collect($cohorts)
            ->filter(fn (array $cohort): bool => ($cohort['first_log_within_7d']['status'] ?? null) === 'mature'
                && ($cohort['first_log_within_7d']['denominator'] ?? 0) >= 10)
            ->sortBy('cohort_month')
            ->values();

        return $eligible->sliding(2)
            ->filter(fn ($pair): bool => $pair->count() === 2)
            ->map(function ($pair): array {
                $from = $pair[0];
                $to = $pair[1];
                $fromMetric = $from['first_log_within_7d'];
                $toMetric = $to['first_log_within_7d'];
                $delta = round($toMetric['percentage'] - $fromMetric['percentage'], 1);

                return [
                    'metric' => 'Eerste log ≤7 dagen',
                    'from_cohort' => $from['cohort_month'],
                    'to_cohort' => $to['cohort_month'],
                    'from' => $fromMetric,
                    'to' => $toMetric,
                    'delta_percentage_points' => $delta,
                    'interpretation' => abs($delta) >= 15
                        ? 'Opvallend verschil; nog geen structurele trend.'
                        : 'Geen duidelijke structurele verandering.',
                ];
            })
            ->values()
            ->all();
    }
}
