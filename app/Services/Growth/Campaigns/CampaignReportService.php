<?php

namespace App\Services\Growth\Campaigns;

use App\Models\GrowthProspect;
use App\Services\Growth\GrowthCampaignEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class CampaignReportService
{
    public function __construct(
        private readonly CampaignImportService $importer,
        private readonly CampaignCleanupService $cleanup,
        private readonly GrowthCampaignEligibilityService $eligibility,
    ) {}

    /**
     * @return array{seed urls:int,discovered:int,accepted:int,ready for outreach:int,needs email:int,manual review:int,invalid email:int,duplicates/skipped:int,top_ready:array<int, array{name:string,website:?string,email:?string,confidence:?int}>,top_attention:array<int, array{name:string,email_status:string,lifecycle_status:string,skip_reason:?string,website:?string,duplicate:?int}>}
     */
    public function report(CampaignDefinition $definition, ?string $seedPath = null, ?string $discoveredPath = null, ?string $reportPath = null): array
    {
        $campaign = $this->importer->campaign($definition);
        $query = GrowthProspect::query()->where('campaign_id', $campaign->id);
        $seedPath = $seedPath ?? $definition->seedUrlsPath();
        $discoveredPath = $discoveredPath ?? $definition->discoveredCsvPath();
        $reportPath = $reportPath ?? $definition->reportPath();

        $report = [
            'seed urls' => $this->countLines($seedPath),
            'discovered' => max(0, $this->countLines($discoveredPath) - 1),
            'accepted' => (clone $query)->whereNull('duplicate_of_id')->where(function (Builder $query): void {
                $query->whereNotNull('website')->orWhereNotNull('normalized_domain');
            })->count(),
            'ready for outreach' => (clone $query)->get()->filter(fn (GrowthProspect $prospect): bool => $this->eligibility->firstBlockingReason($prospect, $campaign) === null)->count(),
            'needs email' => (clone $query)->whereNull('duplicate_of_id')->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)->count(),
            'manual review' => (clone $query)->whereNull('duplicate_of_id')->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)->count(),
            'invalid email' => (clone $query)->whereNull('duplicate_of_id')->where('email_status', GrowthProspect::EMAIL_STATUS_INVALID)->count(),
            'duplicates/skipped' => (clone $query)->where(function (Builder $query): void {
                $query->whereNotNull('duplicate_of_id')->orWhereNotNull('skip_reason');
            })->count(),
            'top_ready' => (clone $query)->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)->whereNotNull('email')->orderByDesc('suggested_email_confidence')->orderBy('name')->limit(25)->get(['name', 'website', 'email', 'suggested_email_confidence'])->map(fn (GrowthProspect $prospect): array => ['name' => $prospect->name, 'website' => $prospect->website, 'email' => $prospect->email, 'confidence' => $prospect->suggested_email_confidence])->all(),
            'top_attention' => $this->cleanup->attentionRecords($definition, 25)->map(fn (GrowthProspect $prospect): array => ['name' => $prospect->name, 'email_status' => (string) $prospect->email_status, 'lifecycle_status' => (string) $prospect->lifecycle_status, 'skip_reason' => $prospect->skip_reason, 'website' => $prospect->website, 'duplicate' => $prospect->duplicate_of_id])->all(),
        ];

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $report;
    }

    private function countLines(string $path): int
    {
        if ($path === '' || ! is_file($path)) {
            return 0;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return is_array($lines) ? count($lines) : 0;
    }
}
