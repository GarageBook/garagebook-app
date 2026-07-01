<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CampaignReportCommand extends Command
{
    protected $signature = 'garagebook:campaign-report {campaign}';

    protected $description = 'Dispatch report voor een campaign.';

    public function handle(): int
    {
        return match ($this->argument('campaign')) {
            'community2026' => $this->call('garagebook:community2026-report'),
            'partner2026' => $this->call('garagebook:partner2026-report'),
            default => $this->failUnknownCampaign(),
        };
    }

    private function failUnknownCampaign(): int
    {
        $this->error('Onbekende campaign.');

        return self::FAILURE;
    }
}
