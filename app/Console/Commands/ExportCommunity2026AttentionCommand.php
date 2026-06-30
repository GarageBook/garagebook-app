<?php

namespace App\Console\Commands;

use App\Services\Growth\Community2026AttentionReviewService;
use Illuminate\Console\Command;

class ExportCommunity2026AttentionCommand extends Command
{
    protected $signature = 'garagebook:community2026-attention-export
        {--output=storage/app/imports/community2026_attention.csv : Output CSV pad}
        {--limit=20 : Max aantal attention records om te exporteren}';

    protected $description = 'Exporteer Community2026 attention records naar een review CSV zonder te mailen of te queuen.';

    public function handle(Community2026AttentionReviewService $service): int
    {
        $output = (string) $this->option('output');
        $limit = max(1, (int) $this->option('limit'));
        $summary = $service->exportCsv($output, $limit);
        $path = $this->resolvePath($output);
        $rows = $this->readCsv($path);

        $this->info('Community2026 attention export voltooid.');
        $this->line('attention records: '.$summary['attention_records']);
        $this->line('suggested_email gevonden: '.$summary['suggested_email_found']);
        $this->line('contact_url gevonden: '.$summary['contact_url_found']);
        $this->line('onterecht invalid vermoedelijk: '.$summary['possible_invalid']);
        $this->newLine();

        $this->table(
            ['name', 'email_status', 'lifecycle_status', 'skip_reason', 'suggested_email', 'contact_url'],
            array_slice(array_map(static fn (array $row): array => [
                $row[0] ?? '',
                $row[3] ?? '',
                $row[4] ?? '',
                $row[5] ?? '',
                $row[6] ?? '',
                $row[7] ?? '',
            ], array_slice($rows, 1)), 0, 20),
        );

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
