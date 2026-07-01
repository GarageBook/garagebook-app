<?php

namespace App\Console\Commands;

use App\Models\GrowthProspect;
use App\Services\Growth\Community2026ImportService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class ReportCommunity2026Command extends Command
{
    protected $signature = 'garagebook:community2026-report
        {--seed=storage/app/imports/community2026_seed_urls.txt : Seed URL bestand}
        {--discovered=storage/app/imports/community2026_discovered.csv : Discovery CSV bestand}
        {--report=storage/app/imports/community2026_report.json : Report JSON output}';

    protected $description = 'Rapporteer Community2026 seed, discovery, acceptatie, review, ready en needs-email aantallen.';

    public function handle(Community2026ImportService $importer): int
    {
        $campaign = $importer->campaign();
        $query = GrowthProspect::query()->where('campaign_id', $campaign->id);

        $report = [
            'seed urls' => $this->countLines((string) $this->option('seed')),
            'discovered' => max(0, $this->countLines((string) $this->option('discovered')) - 1),
            'accepted' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('website')->orWhereNotNull('normalized_domain');
                })
                ->count(),
            'manual review' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)
                ->count(),
            'ready' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
                ->count(),
            'needs email' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)
                ->count(),
        ];

        $reportPath = $this->resolvePath((string) $this->option('report'));
        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info('Community2026 report.');
        foreach ($report as $label => $count) {
            $this->line($label.': '.$count);
        }
        $this->line('report file: '.$reportPath);
        $this->warn('Er is niets gemaild en niets gequeued.');

        return self::SUCCESS;
    }

    private function countLines(string $path): int
    {
        $path = $this->resolvePath($path);

        if (! is_file($path)) {
            return 0;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return is_array($lines) ? count($lines) : 0;
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
}
