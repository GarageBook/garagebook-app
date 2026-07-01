<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CampaignQualityCommand extends Command
{
    protected $signature = 'garagebook:campaign-quality {campaign}';

    protected $description = 'Dispatch quality filter voor een campaign.';

    public function handle(): int
    {
        return match ($this->argument('campaign')) {
            'community2026' => $this->call('garagebook:community2026-quality-filter'),
            'partner2026' => $this->call('garagebook:partner2026-quality-filter'),
            default => $this->failUnknownCampaign(),
        };
    }

    private function failUnknownCampaign(): int
    {
        $this->error('Onbekende campaign.');

        return self::FAILURE;
    }
}
