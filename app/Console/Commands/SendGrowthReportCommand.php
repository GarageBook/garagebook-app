<?php

namespace App\Console\Commands;

use App\Mail\WeeklyGrowthReportMail;
use App\Support\Growth\GrowthDashboardData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendGrowthReportCommand extends Command
{
    protected $signature = 'garagebook:send-growth-report';

    protected $description = 'Verstuurt de wekelijkse GarageBook activation/retention rapportage per e-mail.';

    public function handle(GrowthDashboardData $growthDashboardData): int
    {
        $recipient = config('services.growth_report.recipient');

        if (! is_string($recipient) || $recipient === '') {
            $this->warn('Geen growth report ontvanger geconfigureerd.');

            return self::SUCCESS;
        }

        Mail::to($recipient)->send(new WeeklyGrowthReportMail($growthDashboardData->weeklyGrowthReport()));

        $this->info('Growth report verzonden naar: '.$recipient);

        return self::SUCCESS;
    }
}
