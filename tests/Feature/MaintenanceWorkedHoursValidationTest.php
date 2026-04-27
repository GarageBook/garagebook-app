<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWorkedHoursValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_worked_hours_accepts_large_numeric_value_without_overflowing_model_attribute(): void
    {
        $log = new MaintenanceLog();
        $log->worked_hours = '1234';

        $this->assertSame('1234.00', $log->getAttributes()['worked_hours']);
    }
}
