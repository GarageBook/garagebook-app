<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GrowthReportSchedulerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_scheduler_registers_weekly_growth_report_for_monday_at_seven_in_amsterdam(): void
    {
        $event = $this->growthReportEvent();

        $this->assertSame('0 7 * * 1', $event->expression);
        $this->assertSame('Europe/Amsterdam', $event->timezone);
    }

    public function test_weekly_growth_report_runs_at_the_correct_utc_time_in_cet_and_cest(): void
    {
        $event = $this->growthReportEvent();

        $this->assertDueAt($event, '2026-01-05 06:00:00 UTC');
        $this->assertNotDueAt($event, '2026-01-05 05:00:00 UTC');
        $this->assertNotDueAt($event, '2026-01-05 07:00:00 UTC');

        $this->assertDueAt($event, '2026-07-06 05:00:00 UTC');
        $this->assertNotDueAt($event, '2026-07-06 04:00:00 UTC');
        $this->assertNotDueAt($event, '2026-07-06 06:00:00 UTC');
    }

    public function test_weekly_growth_report_runs_once_at_the_correct_time_around_dst_transitions(): void
    {
        $event = $this->growthReportEvent();

        $this->assertSame([
            '2026-03-23 06:00',
            '2026-03-30 05:00',
        ], $this->dueTimesBetween($event, '2026-03-23 00:00:00 UTC', '2026-03-31 00:00:00 UTC'));

        $this->assertSame([
            '2026-10-19 05:00',
            '2026-10-26 06:00',
        ], $this->dueTimesBetween($event, '2026-10-19 00:00:00 UTC', '2026-10-27 00:00:00 UTC'));
    }

    private function growthReportEvent(): Event
    {
        $events = collect(app(Schedule::class)->events());

        $event = $events->first(fn ($event) => str_contains($event->command, 'garagebook:send-growth-report'));

        $this->assertNotNull($event);

        return $event;
    }

    private function assertDueAt(Event $event, string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time));

        $this->assertTrue($event->isDue($this->app), $time);
    }

    private function assertNotDueAt(Event $event, string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time));

        $this->assertFalse($event->isDue($this->app), $time);
    }

    /**
     * @return list<string>
     */
    private function dueTimesBetween(Event $event, string $start, string $end): array
    {
        $dueTimes = [];
        $time = Carbon::parse($start);
        $end = Carbon::parse($end);

        while ($time->lessThan($end)) {
            Carbon::setTestNow($time);

            if ($event->isDue($this->app)) {
                $dueTimes[] = $time->format('Y-m-d H:i');
            }

            $time->addMinute();
        }

        return $dueTimes;
    }
}
