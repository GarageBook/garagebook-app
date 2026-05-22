<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('backups.enabled')) {
    Schedule::command('backup:run-disaster-recovery')
        ->dailyAt(config('backups.schedule_at', '02:30'))
        ->withoutOverlapping();
}

foreach (['07:00', '13:00', '19:00'] as $time) {
    Schedule::command('garagebook:sync-ga4-analytics')
        ->dailyAt($time)
        ->withoutOverlapping();

    Schedule::command('garagebook:sync-search-console')
        ->dailyAt($time)
        ->withoutOverlapping();
}
