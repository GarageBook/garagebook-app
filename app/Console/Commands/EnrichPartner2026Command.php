<?php

namespace App\Console\Commands;

use App\Services\Growth\Partner2026\Partner2026EnrichmentService;
use Illuminate\Console\Command;

class EnrichPartner2026Command extends Command
{
    protected $signature = 'garagebook:partner2026-enrich
        {--limit= : Max aantal prospects om te verrijken}';

    protected $description = 'Verrijk Partner2026 prospects met publieke e-mailadressen zonder te mailen of te queuen.';

    public function handle(Partner2026EnrichmentService $service): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $summary = $service->enrich($limit);

        $this->info('Partner2026 enrichment voltooid.');
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
