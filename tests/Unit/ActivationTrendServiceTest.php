<?php

namespace Tests\Unit;

use App\Services\Growth\ActivationTrendService;
use PHPUnit\Framework\TestCase;

class ActivationTrendServiceTest extends TestCase
{
    public function test_multiple_mature_cohorts_produce_unique_chronological_windows(): void
    {
        $cohorts = [
            $this->cohort('2026-08', 18, 30),
            $this->cohort('2026-06', 10, 25),
            $this->cohort('2026-04', 5, 20),
            $this->cohort('2026-07', 15, 30),
            $this->cohort('2026-05', 9, 20),
        ];

        $trends = (new ActivationTrendService)->compare($cohorts);

        $this->assertSame([
            ['2026-04', '2026-05'],
            ['2026-05', '2026-06'],
            ['2026-06', '2026-07'],
            ['2026-07', '2026-08'],
        ], array_map(fn (array $trend): array => [$trend['from_cohort'], $trend['to_cohort']], $trends));
        $this->assertCount(4, array_unique(array_map(
            fn (array $trend): string => $trend['from_cohort'].'>'.$trend['to_cohort'],
            $trends,
        )));
        $this->assertNotContains(null, array_column($trends, 'from_cohort'));
        $this->assertNotContains(null, array_column($trends, 'to_cohort'));
        $this->assertSame(5, $trends[0]['from']['numerator']);
        $this->assertSame(20, $trends[0]['to']['denominator']);
        $this->assertSame(20.0, $trends[0]['delta_percentage_points']);
        $this->assertSame(-5.0, $trends[1]['delta_percentage_points']);
        $this->assertSame(10.0, $trends[2]['delta_percentage_points']);
        $this->assertSame(10.0, $trends[3]['delta_percentage_points']);
    }

    public function test_only_mature_comparable_cohorts_are_compared_without_structural_claim(): void
    {
        $cohorts = [
            $this->cohort('2026-09', 5, 10, 'provisional'),
            $this->cohort('2026-08', 14, 22, 'mature'),
            $this->cohort('2026-07', 13, 40, 'mature'),
            $this->cohort('2026-06', 2, 5, 'mature'),
        ];

        $trends = (new ActivationTrendService)->compare($cohorts);

        $this->assertCount(1, $trends);
        $this->assertSame('2026-07', $trends[0]['from_cohort']);
        $this->assertSame('2026-08', $trends[0]['to_cohort']);
        $this->assertSame('Opvallend verschil; nog geen structurele trend.', $trends[0]['interpretation']);
        $this->assertSame(13, $trends[0]['from']['numerator']);
        $this->assertSame(22, $trends[0]['to']['denominator']);
    }

    public function test_invalid_and_ineligible_cohorts_are_skipped_without_incomplete_trends(): void
    {
        $cohorts = [
            [],
            ['cohort_month' => null, 'first_log_within_7d' => $this->metric(5, 10)],
            ['cohort_month' => '2026-04'],
            $this->cohort('2026-05', 0, 0, 'n/a'),
            $this->cohort('2026-06', 5, 10, 'provisional'),
            $this->cohort('2026-07', 4, 9),
            ['cohort_month' => '2026-08', 'first_log_within_7d' => [
                'numerator' => 5,
                'denominator' => 10,
                'percentage' => null,
                'status' => 'mature',
            ]],
            $this->cohort('2026-09', 6, 10),
        ];

        $this->assertSame([], (new ActivationTrendService)->compare($cohorts));
    }

    private function cohort(string $month, int $numerator, int $denominator, string $status = 'mature'): array
    {
        return [
            'cohort_month' => $month,
            'first_log_within_7d' => $this->metric($numerator, $denominator, $status),
        ];
    }

    private function metric(int $numerator, int $denominator, string $status = 'mature'): array
    {
        return [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'percentage' => $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : null,
            'status' => $status,
        ];
    }
}
