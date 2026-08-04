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
        $recipients = $this->growthReportRecipients();

        if ($recipients === []) {
            $this->warn('Geen growth report ontvanger geconfigureerd.');

            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new WeeklyGrowthReportMail($growthDashboardData->weeklyGrowthReport()));

        $this->info('Growth report verzonden naar: '.implode(', ', $recipients));

        return self::SUCCESS;
    }

    private function growthReportRecipients(): array
    {
        $configuredRecipients = config('services.growth_report.recipients');

        if (is_string($configuredRecipients)) {
            $configuredRecipients = explode(',', $configuredRecipients);
        }

        if (! is_array($configuredRecipients) || $configuredRecipients === []) {
            $configuredRecipients = [config('services.growth_report.recipient')];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($recipient): string => trim((string) $recipient),
            $configuredRecipients,
        ))));
    }
}
