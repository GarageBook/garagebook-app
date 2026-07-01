<?php

namespace App\Console\Commands;

use App\Services\Growth\GrowthCampaignQueueService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class QueueGrowthCampaignCommand extends Command
{
    protected $signature = 'garagebook:queue-growth-campaign {campaign_slug} {--limit= : Maximaal aantal prospects voor deze batch} {--dry-run : Toon alleen welke prospects geselecteerd zouden worden}';

    protected $description = 'Zet ready growth prospects klaar voor een campagne met anti-duplicate en anti-spam checks.';

    public function handle(GrowthCampaignQueueService $queue): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        try {
            if ($this->option('dry-run')) {
                $result = $queue->preview((string) $this->argument('campaign_slug'), $limit);

                $this->info('Dry-run pilot queue voor '.$this->argument('campaign_slug').'.');
                foreach ($result['summary'] as $label => $count) {
                    $this->line($label.': '.$count);
                }
                $this->warn('Er is niets gemaild en niets gequeued.');

                if ($result['selected'] !== []) {
                    $this->newLine();
                    $this->table(
                        ['Naam', 'Website', 'E-mail', 'Subtype', 'Quality', 'Laatste contact'],
                        array_map(static fn (array $row): array => [
                            $row['name'],
                            $row['website'] ?? '-',
                            $row['email'] ?? '-',
                            $row['subtype'] ?? '-',
                            $row['quality_score'] ?? '-',
                            $row['last_contacted_at'] ?? '-',
                        ], $result['selected']),
                    );
                }

                return self::SUCCESS;
            }

            $summary = $queue->queue((string) $this->argument('campaign_slug'), $limit);
        } catch (ModelNotFoundException) {
            $this->error('Growth campagne niet gevonden: '.$this->argument('campaign_slug'));

            return self::FAILURE;
        }

        $this->info('Pilot queue klaar voor '.$this->argument('campaign_slug').'.');
        foreach ($summary as $label => $count) {
            $this->line($label.': '.$count);
        }
        $this->warn('Er is niets gemaild.');

        return self::SUCCESS;
    }
}
