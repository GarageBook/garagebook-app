<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class GrowthReportSchedulerTest extends TestCase
{
    public function test_scheduler_registers_weekly_growth_report_command(): void
    {
        $events = collect(app(Schedule::class)->events());

        $event = $events->first(fn ($event) => str_contains($event->command, 'garagebook:send-growth-report'));

        $this->assertNotNull($event);
        $this->assertStringContainsString('0 9 * * 1', $event->expression);
    }
}
