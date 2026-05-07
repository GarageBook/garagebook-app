<?php

namespace Tests\Unit;

use App\Services\DistanceUnitService;
use Tests\TestCase;

class DistanceUnitServiceTest extends TestCase
{
    public function test_it_converts_kilometers_to_miles_for_display(): void
    {
        $service = app(DistanceUnitService::class);

        $this->assertSame(100.0, $service->fromKilometers(160.9344, DistanceUnitService::UNIT_MILES, 1));
        $this->assertSame('100,0 mi', $service->formatFromKilometers(160.9344, DistanceUnitService::UNIT_MILES, 1));
    }

    public function test_it_converts_miles_back_to_kilometers_for_storage(): void
    {
        $service = app(DistanceUnitService::class);

        $this->assertSame(160.9, $service->toKilometers(100, DistanceUnitService::UNIT_MILES, 1));
        $this->assertSame(160934.0, $service->toKilometers(100000, DistanceUnitService::UNIT_MILES, 0));
    }
}
