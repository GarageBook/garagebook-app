<?php

namespace App\Console\Commands;

use App\Services\Gsc\GscCsvImportService;
use Illuminate\Console\Command;

class ImportGscCsvCommand extends Command
{
    protected $signature = 'garagebook:gsc:import-csv
        {--pages= : Pad naar GSC pagina CSV}
        {--queries= : Pad naar GSC zoekopdracht CSV}
        {--date= : Snapshotdatum, bijvoorbeeld 2026-07-08}';

    protected $description = 'Import Google Search Console page and query CSV exports into daily snapshots.';

    public function handle(GscCsvImportService $importer): int
    {
        $date = trim((string) $this->option('date'));

        if ($date === '') {
            $this->error('Optie --date is verplicht.');

            return self::FAILURE;
        }

        $pages = trim((string) $this->option('pages')) ?: null;
        $queries = trim((string) $this->option('queries')) ?: null;

        if ($pages === null && $queries === null) {
            $this->error('Geef minimaal --pages of --queries mee.');

            return self::FAILURE;
        }

        $summary = $importer->import($pages, $queries, $date);

        $this->line('GSC snapshot date: '.$summary['date']);
        $this->line('Page rows imported: '.$summary['pages']);
        $this->line('Query rows imported: '.$summary['queries']);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
