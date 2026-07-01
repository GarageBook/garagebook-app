<?php

namespace App\Console\Commands;

use App\Models\GrowthProspect;
use App\Services\Growth\GrowthCampaignEligibilityService;
use App\Services\Growth\Partner2026\Partner2026CleanupService;
use App\Services\Growth\Partner2026\Partner2026ImportService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class ReportPartner2026Command extends Command
{
    protected $signature = 'garagebook:partner2026-report
        {--seed=storage/app/imports/partner2026_seed_urls.txt : Seed URL bestand}
        {--discovered=storage/app/imports/partner2026_discovered.csv : Discovery CSV bestand}
        {--report=storage/app/imports/partner2026_report.json : Report JSON output}';

    protected $description = 'Rapporteer Partner2026 seed, discovery, acceptatie, review, ready en needs-email aantallen.';

    public function handle(Partner2026ImportService $importer, Partner2026CleanupService $cleanup, GrowthCampaignEligibilityService $eligibility): int
    {
        $campaign = $importer->campaign();
        $query = GrowthProspect::query()->where('campaign_id', $campaign->id);

        $readyProspects = (clone $query)
            ->whereNull('duplicate_of_id')
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
            ->get()
            ->filter(fn (GrowthProspect $prospect): bool => $eligibility->firstBlockingReason($prospect, $campaign) === null)
            ->values();

        $attentionRecords = $cleanup->attentionRecords(25);

        $report = [
            'seed urls' => $this->countLines((string) $this->option('seed')),
            'discovered' => max(0, $this->countLines((string) $this->option('discovered')) - 1),
            'accepted' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('website')->orWhereNotNull('normalized_domain');
                })
                ->count(),
            'ready for outreach' => $readyProspects->count(),
            'needs email' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)
                ->count(),
            'manual review' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)
                ->count(),
            'invalid email' => (clone $query)
                ->whereNull('duplicate_of_id')
                ->where('email_status', GrowthProspect::EMAIL_STATUS_INVALID)
                ->count(),
            'duplicates/skipped' => (clone $query)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('duplicate_of_id')->orWhereNotNull('skip_reason');
                })
                ->count(),
        ];

        $reportPath = $this->resolvePath((string) $this->option('report'));
        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->info('Partner2026 report.');
        foreach ($report as $label => $count) {
            $this->line($label.': '.$count);
        }
        $this->line('report file: '.$reportPath);
        $this->warn('Er is niets gemaild en niets gequeued.');

        $this->newLine();
        $this->table(
            ['Naam', 'Website', 'E-mail', 'Status'],
            $readyProspects->take(25)->map(fn (GrowthProspect $prospect): array => [
                $prospect->name,
                $prospect->website ?? '-',
                $prospect->email ?? '-',
                $prospect->lifecycle_status,
            ])->all(),
        );

        $this->newLine();
        $this->table(
            ['Naam', 'Website', 'E-mailstatus', 'Lifecycle', 'Skip reason'],
            $attentionRecords->map(fn (GrowthProspect $prospect): array => [
                $prospect->name,
                $prospect->website ?? '-',
                $prospect->email_status,
                $prospect->lifecycle_status,
                $prospect->skip_reason ?? '-',
            ])->all(),
        );

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

        if (preg_match('/^[A-Za-z]:[\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
