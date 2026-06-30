<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthCampaignQueueService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class QueueGrowthCampaignCommand extends Command
{
    protected $signature = 'garagebook:queue-growth-campaign {campaign_slug}';

    protected $description = 'Zet ready growth prospects klaar voor een campagne met anti-duplicate en anti-spam checks.';

    public function handle(GrowthCampaignQueueService $queue): int
    {
        try {
            $summary = $queue->queue((string) $this->argument('campaign_slug'));
        } catch (ModelNotFoundException) {
            $this->error('Growth campagne niet gevonden: '.$this->argument('campaign_slug'));

            return self::FAILURE;
        }

        foreach ($summary as $label => $count) {
            $this->line($label.': '.$count);
        }

        return self::SUCCESS;
    }
}
