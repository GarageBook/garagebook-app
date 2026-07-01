<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CampaignImportCommand extends Command
{
    protected $signature = 'garagebook:campaign-import {campaign} {--file= : Inputbestand}';

    protected $description = 'Dispatch import voor een campaign.';

    public function handle(): int
    {
        $campaign = (string) $this->argument('campaign');
        $file = (string) $this->option('file');

        return match ($campaign) {
            'community2026' => $this->call('garagebook:growth-import-community2026', ['--file' => $file]),
            'partner2026' => $this->call('garagebook:growth-import-partner2026', ['--file' => $file]),
            default => $this->failUnknownCampaign(),
        };
    }

    private function failUnknownCampaign(): int
    {
        $this->error('Onbekende campaign.');

        return self::FAILURE;
    }
}
