<?php

namespace App\Console\Commands;

use App\Services\Gsc\GscCsvImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportGscFolderCommand extends Command
{
    protected $signature = 'garagebook:gsc:import-folder
        {--path= : Map met GSC CSV-bestanden}
        {--date= : Snapshotdatum, bijvoorbeeld 2026-07-08}
        {--replace : Vervang bestaande data voor deze datum}';

    protected $description = 'Import all Google Search Console CSV exports from a folder through one bulk import session.';

    public function handle(GscCsvImportService $importer): int
    {
        $date = trim((string) $this->option('date'));
        $path = trim((string) $this->option('path'));

        if ($date === '') {
            $this->error('Optie --date is verplicht.');

            return self::FAILURE;
        }

        if ($path === '') {
            $this->error('Optie --path is verplicht.');

            return self::FAILURE;
        }

        $directory = $this->resolvePath($path);

        if (! is_dir($directory)) {
            $this->error('Map niet gevonden: '.$directory);

            return self::FAILURE;
        }

        $files = collect(glob($directory.DIRECTORY_SEPARATOR.'*.csv') ?: [])
            ->sort()
            ->values()
            ->all();

        if ($files === []) {
            $this->warn('Geen CSV-bestanden gevonden in: '.$directory);

            return self::FAILURE;
        }

        $summary = $importer->importBulkSession($files, $date, (bool) $this->option('replace'));

        $this->line('GSC import session: '.$summary['session_id']);
        $this->line('Snapshot date: '.$summary['date']);
        $this->line('Status: '.$summary['status']);
        $this->line('Processed files: '.$summary['processed_files']);
        $this->line('Skipped files: '.$summary['skipped_files']);
        $this->line('Page rows imported: '.$summary['pages_imported']);
        $this->line('Query rows imported: '.$summary['queries_imported']);
        $this->line('Country rows imported: '.$summary['countries_imported']);
        $this->line('Device rows imported: '.$summary['devices_imported']);
        $this->line('Search appearance rows imported: '.$summary['search_appearances_imported']);
        $this->line('Date rows imported: '.$summary['date_rows_imported']);

        foreach ($summary['warnings'] ?? [] as $warning) {
            $this->warn($warning);
        }

        foreach ($summary['errors'] ?? [] as $error) {
            $this->error($error);
        }

        return ($summary['errors'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resolvePath(string $path): string
    {
        if (Str::startsWith($path, ['/'])) {
            return $path;
        }

        return base_path($path);
    }
}
