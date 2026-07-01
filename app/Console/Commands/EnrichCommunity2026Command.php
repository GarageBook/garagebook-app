<?php

namespace App\Console\Commands;

use App\Services\Growth\Community2026EnrichmentService;
use Illuminate\Console\Command;

class EnrichCommunity2026Command extends Command
{
    protected $signature = 'garagebook:community2026-enrich
        {--limit= : Max aantal prospects om te verrijken}';

    protected $description = 'Verrijk Community2026 prospects met publieke e-mailadressen zonder te mailen of te queuen.';

    public function handle(Community2026EnrichmentService $service): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $summary = $service->enrich($limit);

        $this->info('Community2026 enrichment voltooid.');
        $this->line('prospects gescand: '.$summary['scanned']);
        $this->line('e-mails automatisch gevonden: '.$summary['auto_found']);
        $this->line('suggested_email gevonden: '.$summary['suggested_found']);
        $this->line('nog steeds missing: '.$summary['still_missing']);
        $this->warn('Er is niets gemaild en niets gequeued.');

        $this->newLine();
        $this->table(
            ['Naam', 'Website', 'E-mail', 'Confidence'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['website'] ?? '-',
                $row['email'] ?? '-',
                $row['confidence'] ?? '-',
            ], $summary['ready_top_50']),
        );

        return self::SUCCESS;
    }
}
