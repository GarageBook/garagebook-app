<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CampaignEnrichCommand extends Command
{
    protected $signature = 'garagebook:campaign-enrich {campaign} {--limit= : Max aantal prospects om te verrijken}';

    protected $description = 'Dispatch enrichment voor een campaign.';

    public function handle(): int
    {
        $campaign = (string) $this->argument('campaign');
        $options = [];

        if ($this->option('limit') !== null) {
            $options['--limit'] = $this->option('limit');
        }

        return match ($campaign) {
            'community2026' => $this->call('garagebook:community2026-enrich', $options),
            'partner2026' => $this->call('garagebook:partner2026-enrich', $options),
            default => $this->failUnknownCampaign(),
        };
    }

    private function failUnknownCampaign(): int
    {
        $this->error('Onbekende campaign.');

        return self::FAILURE;
    }
}
