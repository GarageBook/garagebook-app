<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CampaignDiscoverCommand extends Command
{
    protected $signature = 'garagebook:campaign-discover {campaign}';

    protected $description = 'Dispatch discovery voor een campaign.';

    public function handle(): int
    {
        return match ($this->argument('campaign')) {
            'community2026' => $this->call('garagebook:discover-community2026'),
            'partner2026' => $this->call('garagebook:discover-partner2026'),
            default => $this->failUnknownCampaign(),
        };
    }

    private function failUnknownCampaign(): int
    {
        $this->error('Onbekende campaign.');

        return self::FAILURE;
    }
}
