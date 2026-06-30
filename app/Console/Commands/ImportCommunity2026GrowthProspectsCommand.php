<?php

namespace App\Console\Commands;

use App\Services\Growth\Community2026ImportService;
use Illuminate\Console\Command;

class ImportCommunity2026GrowthProspectsCommand extends Command
{
    protected $signature = 'garagebook:growth-import-community2026 {--file=storage/app/imports/community2026.csv : CSV of JSON inputbestand}';

    protected $description = 'Importeer Community2026 growth prospects uit CSV of JSON zonder te mailen.';

    public function handle(Community2026ImportService $importer): int
    {
        $path = $this->resolvePath((string) $this->option('file'));

        if (! is_file($path)) {
            $this->error('Inputbestand niet gevonden: '.$path);

            return self::FAILURE;
        }

        $result = $importer->importPath($path);

        $this->info('Community2026 import voltooid.');
        $this->line('created: '.$result['created']);
        $this->line('updated: '.$result['updated']);
        $this->line('imported events: '.$result['imported']);
        $this->line('enriched events: '.$result['enriched']);
        $this->line('skipped: '.$result['skipped']);

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $basePath = base_path($path);

        return is_file($basePath) ? $basePath : $path;
    }
}
