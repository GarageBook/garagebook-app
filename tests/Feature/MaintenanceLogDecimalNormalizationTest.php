<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceLogDecimalNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_accepts_dutch_decimal_format(): void
    {
        $log = new MaintenanceLog();
        $log->cost = '1.323,32';

        $this->assertSame('1323.32', $log->getAttributes()['cost']);
    }

    public function test_worked_hours_accepts_comma_decimal_format(): void
    {
        $log = new MaintenanceLog();
        $log->worked_hours = '2,5';

        $this->assertSame('2.50', $log->getAttributes()['worked_hours']);
    }
}
