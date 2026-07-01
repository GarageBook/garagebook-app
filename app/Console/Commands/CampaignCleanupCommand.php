<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CampaignCleanupCommand extends Command
{
    protected $signature = 'garagebook:campaign-cleanup {campaign}';

    protected $description = 'Dispatch cleanup voor een campaign.';

    public function handle(): int
    {
        return match ($this->argument('campaign')) {
            'community2026' => $this->call('garagebook:community2026-cleanup'),
            'partner2026' => $this->call('garagebook:partner2026-cleanup'),
            default => $this->failUnknownCampaign(),
        };
    }

    private function failUnknownCampaign(): int
    {
        $this->error('Onbekende campaign.');

        return self::FAILURE;
    }
}
