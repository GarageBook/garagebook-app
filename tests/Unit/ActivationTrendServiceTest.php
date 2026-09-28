<?php

namespace Tests\Unit;

use App\Services\Growth\ActivationTrendService;
use PHPUnit\Framework\TestCase;

class ActivationTrendServiceTest extends TestCase
{
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

    private function cohort(string $month, int $numerator, int $denominator, string $status): array
    {
        return [
            'cohort_month' => $month,
            'first_log_within_7d' => [
                'numerator' => $numerator,
                'denominator' => $denominator,
                'percentage' => round(($numerator / $denominator) * 100, 1),
                'status' => $status,
            ],
        ];
    }
}
