<?php

namespace App\Console\Commands;

use App\Services\Growth\Campaigns\CampaignRegistry;
use App\Services\Growth\Campaigns\CampaignReportService;
use Illuminate\Console\Command;

class CampaignReportCommand extends Command
{
    protected $signature = 'garagebook:campaign-report {campaign} {--ready-only : Toon alleen readiness en pilot-safety rapportage} {--seed=} {--discovered=} {--report=}';

    protected $description = 'Rapporteer de growth campaign status.';

    public function handle(CampaignRegistry $registry, CampaignReportService $reports): int
    {
        try {
            $definition = $registry->forSlug((string) $this->argument('campaign'));
        } catch (\InvalidArgumentException $exception) {
            $this->error('Onbekende campaign.');

            return self::FAILURE;
        }

        $seedPath = $this->resolvePath($this->option('seed') ?: $definition->seedUrlsPath());
        $discoveredPath = $this->resolvePath($this->option('discovered') ?: $definition->discoveredCsvPath());
        $reportPath = $this->resolvePath($this->option('report') ?: ($this->option('ready-only') ? $definition->readyReportPath() : $definition->reportPath()));

        if ($this->option('ready-only')) {
            $report = $reports->readyOnlyReport($definition, $seedPath, $discoveredPath, $reportPath);

            $this->info($definition->name().' readiness report.');
            $this->line('seed urls: '.$report['seed urls']);
            $this->line('discovered: '.$report['discovered']);
            $this->line('total ready for outreach: '.$report['total ready for outreach']);
            $this->line('excluded recent contacted: '.$report['excluded recent contacted']);
            $this->line('excluded already received campaign: '.$report['excluded already received campaign']);
            $this->line('excluded duplicate: '.$report['excluded duplicate']);
            $this->line('excluded invalid/missing email: '.$report['excluded invalid/missing email']);
            $this->line('excluded manual review: '.$report['excluded manual review']);
            $this->line('excluded personal email: '.$report['excluded personal email']);
            $this->line('excluded suggested email: '.$report['excluded suggested email']);
            $this->line('report file: '.$reportPath);
            $this->warn('Er is niets gemaild en niets gequeued.');

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
                ], $report['top_ready']),
            );

            return self::SUCCESS;
        }

        $report = $reports->report($definition, $seedPath, $discoveredPath, $reportPath);

        $this->info($definition->name().' report.');
        foreach ($report as $label => $value) {
            if (is_array($value)) {
                continue;
            }

            $this->line($label.': '.$value);
        }
        $this->line('report file: '.$reportPath);
        $this->warn('Er is niets gemaild en niets gequeued.');

        $this->newLine();
        $this->table(
            ['Naam', 'Website', 'E-mail', 'Confidence'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['website'] ?? '-',
                $row['email'] ?? '-',
                $row['confidence'] ?? '-',
            ], $report['top_ready']),
        );

        $this->newLine();
        $this->table(
            ['Naam', 'Website', 'E-mailstatus', 'Lifecycle', 'Skip reason'],
            array_map(static fn (array $row): array => [
                $row['name'],
                $row['website'] ?? '-',
                $row['email_status'],
                $row['lifecycle_status'],
                $row['skip_reason'] ?? '-',
            ], $report['top_attention']),
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
}
