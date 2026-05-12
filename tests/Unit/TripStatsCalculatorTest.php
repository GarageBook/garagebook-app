<?php

namespace Tests\Unit;

use App\Services\Trips\TripStatsCalculator;
use Tests\TestCase;

class TripStatsCalculatorTest extends TestCase
{
    public function test_it_calculates_haversine_distance_between_two_points(): void
    {
        $calculator = app(TripStatsCalculator::class);

        $distanceKm = $calculator->calculateSegmentDistanceKm(52.0907, 5.1214, 52.0950, 5.1410);

        $this->assertEqualsWithDelta(1.42, $distanceKm, 0.15);
    }
}
