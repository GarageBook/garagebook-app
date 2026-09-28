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
            ->filter(fn (array $cohort): bool => $this->isComparable($cohort))
            ->sortBy('cohort_month')
            ->values();

        return $eligible->sliding(2)
            ->map(fn ($pair) => $pair->values())
            ->filter(fn ($pair): bool => $pair->count() === 2
                && $this->isComparable($pair->get(0))
                && $this->isComparable($pair->get(1)))
            ->map(function ($pair): array {
                $from = $pair->get(0);
                $to = $pair->get(1);
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

    /**
     * @param  array<string, mixed>|null  $cohort
     */
    private function isComparable(?array $cohort): bool
    {
        if (! is_string($cohort['cohort_month'] ?? null) || trim($cohort['cohort_month']) === '') {
            return false;
        }

        $metric = $cohort['first_log_within_7d'] ?? null;

        return is_array($metric)
            && ($metric['status'] ?? null) === 'mature'
            && is_numeric($metric['numerator'] ?? null)
            && is_numeric($metric['denominator'] ?? null)
            && is_numeric($metric['percentage'] ?? null)
            && $metric['denominator'] >= 10;
    }
}
