<?php

namespace App\Services\Growth\Campaigns;

use App\Models\GrowthProspect;
use App\Services\Growth\GrowthCampaignEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            'ready for outreach' => (clone $query)->get()->filter(fn (GrowthProspect $prospect): bool => $this->isReadyForOutreach($prospect, $campaign))->count(),
            'needs email' => (clone $query)->whereNull('duplicate_of_id')->where('email_status', GrowthProspect::EMAIL_STATUS_MISSING)->count(),
            'manual review' => (clone $query)->whereNull('duplicate_of_id')->where('lifecycle_status', GrowthProspect::LIFECYCLE_MANUAL_REVIEW)->count(),
            'invalid email' => (clone $query)->whereNull('duplicate_of_id')->where('email_status', GrowthProspect::EMAIL_STATUS_INVALID)->count(),
            'duplicates/skipped' => (clone $query)->where(function (Builder $query): void {
                $query->whereNotNull('duplicate_of_id')->orWhereNotNull('skip_reason');
            })->count(),
            'top_ready' => $this->topReadyRows($query, 25),
            'top_attention' => $this->cleanup->attentionRecords($definition, 25)->map(fn (GrowthProspect $prospect): array => ['name' => $prospect->name, 'email_status' => (string) $prospect->email_status, 'lifecycle_status' => (string) $prospect->lifecycle_status, 'skip_reason' => $prospect->skip_reason, 'website' => $prospect->website, 'duplicate' => $prospect->duplicate_of_id])->all(),
        ];

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $report;
    }

    /**
     * @return array{seed urls:int,discovered:int,total ready for outreach:int,excluded recent contacted:int,excluded already received campaign:int,excluded duplicate:int,excluded invalid/missing email:int,excluded manual review:int,excluded personal email:int,excluded suggested email:int,top_ready:array<int, array{name:string,website:?string,email:?string,subtype:?string,quality_score:?int,last_contacted_at:?string}>}
     */
    public function readyOnlyReport(CampaignDefinition $definition, ?string $seedPath = null, ?string $discoveredPath = null, ?string $reportPath = null): array
    {
        $campaign = $this->importer->campaign($definition);
        $seedPath = $seedPath ?? $definition->seedUrlsPath();
        $discoveredPath = $discoveredPath ?? $definition->discoveredCsvPath();
        $reportPath = $reportPath ?? $definition->readyReportPath();

        $summary = [
            'seed urls' => $this->countLines($seedPath),
            'discovered' => max(0, $this->countLines($discoveredPath) - 1),
            'total ready for outreach' => 0,
            'excluded recent contacted' => 0,
            'excluded already received campaign' => 0,
            'excluded duplicate' => 0,
            'excluded invalid/missing email' => 0,
            'excluded manual review' => 0,
            'excluded personal email' => 0,
            'excluded suggested email' => 0,
        ];

        $readyProspects = collect();

        foreach (GrowthProspect::query()->where('campaign_id', $campaign->id)->orderBy('id')->get() as $prospect) {
            if (filled($prospect->suggested_email)) {
                $summary['excluded suggested email']++;

                continue;
            }

            $reason = $this->eligibility->firstBlockingReason($prospect, $campaign);

            if ($reason === null) {
                $readyProspects->push($prospect);

                continue;
            }

            $summary[$this->readyOnlySummaryKey($reason)]++;
        }

        $summary['total ready for outreach'] = $readyProspects->count();

        $report = $summary + [
            'top_ready' => $this->topReadyRowsFromCollection($readyProspects, 50),
        ];

        File::ensureDirectoryExists(dirname($reportPath));
        File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $report;
    }

    private function readyOnlySummaryKey(string $reason): string
    {
        return match ($reason) {
            GrowthCampaignEligibilityService::REASON_ALREADY_CONTACTED_RECENTLY => 'excluded recent contacted',
            GrowthCampaignEligibilityService::REASON_ALREADY_RECEIVED_CAMPAIGN => 'excluded already received campaign',
            GrowthCampaignEligibilityService::REASON_DUPLICATE => 'excluded duplicate',
            GrowthCampaignEligibilityService::REASON_MISSING_EMAIL,
            GrowthCampaignEligibilityService::REASON_INVALID_EMAIL => 'excluded invalid/missing email',
            GrowthCampaignEligibilityService::REASON_PERSONAL_EMAIL => 'excluded personal email',
            default => 'excluded manual review',
        };
    }

    /**
     * @return array<int, array{name:string,website:?string,email:?string,confidence:?int}>
     */
    private function topReadyRows(Builder $query, int $limit): array
    {
        return (clone $query)
            ->where('lifecycle_status', GrowthProspect::LIFECYCLE_READY)
            ->whereNotNull('email')
            ->whereNull('suggested_email')
            ->get(['name', 'website', 'email', 'quality_score', 'suggested_email_confidence'])
            ->sort(function (GrowthProspect $a, GrowthProspect $b): int {
                $scoreComparison = ((int) ($b->quality_score ?? 0)) <=> ((int) ($a->quality_score ?? 0));

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                return strcmp((string) $a->name, (string) $b->name);
            })
            ->take($limit)
            ->map(fn (GrowthProspect $prospect): array => [
                'name' => $prospect->name,
                'website' => $prospect->website,
                'email' => $prospect->email,
                'confidence' => $prospect->suggested_email_confidence,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, GrowthProspect>  $prospects
     * @return array<int, array{name:string,website:?string,email:?string,subtype:?string,quality_score:?int,last_contacted_at:?string}>
     */
    private function topReadyRowsFromCollection(Collection $prospects, int $limit): array
    {
        return $prospects
            ->sort(function (GrowthProspect $a, GrowthProspect $b): int {
                $scoreComparison = ((int) ($b->quality_score ?? 0)) <=> ((int) ($a->quality_score ?? 0));

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                return strcmp((string) $a->name, (string) $b->name);
            })
            ->take($limit)
            ->map(fn (GrowthProspect $prospect): array => [
                'name' => $prospect->name,
                'website' => $prospect->website,
                'email' => $prospect->email,
                'subtype' => $prospect->prospect_subtype,
                'quality_score' => $prospect->quality_score,
                'last_contacted_at' => $prospect->last_contacted_at instanceof Carbon
                    ? $prospect->last_contacted_at->toDateTimeString()
                    : null,
            ])
            ->values()
            ->all();
    }

    private function isReadyForOutreach(GrowthProspect $prospect, GrowthCampaign $campaign): bool
    {
        return filled($prospect->email)
            && blank($prospect->suggested_email)
            && $this->eligibility->firstBlockingReason($prospect, $campaign) === null;
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
