<?php

namespace App\Console\Commands;

use App\Models\OutreachCampaign;
use App\Models\OutreachEmailLog;
use App\Models\OutreachProspect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncOutreachMasterCommand extends Command
{
    protected $signature = 'garagebook:sync-outreach-master
        {csv_path : Pad naar het master CSV-bestand}
        {--dry-run : Analyseer en schrijf alleen rapportbestanden}
        {--campaign= : Verplichte campaign slug}
        {--force : Voer writes uit zonder interactieve bevestiging}';

    protected $description = 'Synchroniseer outreach prospects veilig tegen een CSV-masterlijst zonder outreach-historie te verwijderen.';

    private const MIN_VALID_ROWS = 50;

    /** @var list<string> */
    private const MASTER_FIELDS = [
        'company_name',
        'email',
        'website',
        'phone',
        'city',
        'province',
        'postal_code',
        'country',
        'source',
        'import_note',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $campaignSlug = trim((string) $this->option('campaign'));

        if ($campaignSlug === '') {
            $this->error('Gebruik --campaign=slug. De campaign is verplicht omdat outreach_prospects een verplichte campaign-relatie hebben.');

            return self::FAILURE;
        }

        $campaign = OutreachCampaign::query()->where('slug', $campaignSlug)->first();

        if (! $campaign instanceof OutreachCampaign) {
            $this->error('Campaign niet gevonden: '.$campaignSlug);

            return self::FAILURE;
        }

        $path = $this->resolveCsvPath((string) $this->argument('csv_path'));

        if (! is_file($path)) {
            $this->error('CSV-bestand niet gevonden: '.$path);

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        $plan = $this->buildPlan($rows, $campaign);

        $this->writeReports($plan['reports']);
        $this->renderSummary($plan, $dryRun);

        if ($plan['summary']['valid_with_email'] < self::MIN_VALID_ROWS) {
            $this->error('Stop: CSV bevat minder dan '.self::MIN_VALID_ROWS.' geldige regels met email. Dit voorkomt sync op een verkeerd of onvolledig bestand.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry-run: er zijn geen databasewijzigingen opgeslagen.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirm('Deze sync gaat prospects aanmaken, updaten en campaign-scoped archiveren. Doorgaan?')) {
            $this->warn('Afgebroken: er zijn geen databasewijzigingen opgeslagen.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($plan): void {
            foreach (array_chunk($plan['updates'], 250) as $chunk) {
                foreach ($chunk as $item) {
                    /** @var OutreachProspect $prospect */
                    $prospect = OutreachProspect::query()->findOrFail($item['prospect_id']);
                    $prospect->forceFill($item['changes'])->save();
                }
            }

            foreach (array_chunk($plan['creates'], 250) as $chunk) {
                foreach ($chunk as $item) {
                    OutreachProspect::query()->create($item['payload']);
                }
            }

            foreach (array_chunk($plan['archives'], 250) as $chunk) {
                foreach ($chunk as $item) {
                    OutreachProspect::query()
                        ->whereKey($item['prospect_id'])
                        ->whereNull('archived_at')
                        ->update(['archived_at' => now()]);
                }
            }
        });

        $this->info('Sync voltooid.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildPlan(array $rows, OutreachCampaign $campaign): array
    {
        $summary = [
            'total_csv' => count($rows),
            'valid_with_email' => 0,
            'skipped_without_email' => 0,
            'matched_by_email' => 0,
            'matched_by_website' => 0,
            'matched_by_company' => 0,
            'review_conflicts' => 0,
            'to_create' => 0,
            'to_archive' => 0,
            'updates' => 0,
            'untouched' => 0,
            'errors' => 0,
            'existing_with_preserved_logs' => $this->countCampaignProspectsWithMailHistory($campaign),
        ];

        $reports = [
            'create' => [],
            'update' => [],
            'review' => [],
            'archive' => [],
            'skipped' => [],
        ];
        $updates = [];
        $creates = [];
        $archives = [];
        $csvProspectIds = [];
        $seenEmails = [];

        foreach ($rows as $row) {
            $email = $this->normalizeEmail($row['email']);

            if ($email === null) {
                $summary['skipped_without_email']++;
                $reports['skipped'][] = $this->reportRow('skipped', $row, null, 'missing_email', 'CSV-regel heeft geen email.');

                continue;
            }

            $summary['valid_with_email']++;

            if (isset($seenEmails[$email])) {
                $summary['errors']++;
                $reports['skipped'][] = $this->reportRow('skipped', $row, null, 'duplicate_csv_email', 'Email staat meerdere keren in de CSV.');

                continue;
            }

            $seenEmails[$email] = true;

            $emailMatch = $this->findByEmail($email);

            if ($emailMatch instanceof OutreachProspect) {
                $summary['matched_by_email']++;
                $csvProspectIds[$emailMatch->id] = true;
                $changes = $this->changedMasterFields($emailMatch, $row);

                if ($changes === []) {
                    $summary['untouched']++;
                } else {
                    $summary['updates']++;
                    $updates[] = [
                        'prospect_id' => $emailMatch->id,
                        'changes' => $changes,
                    ];
                    $reports['update'][] = $this->reportRow('update', $row, $emailMatch, 'email', 'Bestaande prospect matched op email.', $changes);
                }

                continue;
            }

            $websiteMatch = $this->findByWebsite($this->normalizeWebsiteDomain($row['website']));

            if ($websiteMatch instanceof OutreachProspect) {
                $summary['matched_by_website']++;
                $summary['review_conflicts']++;
                $csvProspectIds[$websiteMatch->id] = true;
                $reports['review'][] = $this->reportRow('review', $row, $websiteMatch, 'website', 'Website matcht, maar email wijkt af. Niet automatisch overschreven.');

                continue;
            }

            $companyMatch = $this->findByCompanyName($this->normalizeCompanyName($row['company_name']));

            if ($companyMatch instanceof OutreachProspect) {
                $summary['matched_by_company']++;
                $summary['review_conflicts']++;
                $csvProspectIds[$companyMatch->id] = true;
                $reports['review'][] = $this->reportRow('review', $row, $companyMatch, 'company_name', 'Company_name matcht, maar email/website wijkt af. Niet automatisch overschreven.');

                continue;
            }

            $fuzzyMatch = $this->findFuzzyCompanyName($row['company_name'], $campaign);

            if ($fuzzyMatch instanceof OutreachProspect) {
                $summary['review_conflicts']++;
                $csvProspectIds[$fuzzyMatch->id] = true;
                $reports['review'][] = $this->reportRow('review', $row, $fuzzyMatch, 'fuzzy_company_name', 'Mogelijke fuzzy company_name match. Alleen review, geen automatische merge.');

                continue;
            }

            $summary['to_create']++;
            $payload = $this->createPayload($campaign, $row);
            $creates[] = ['payload' => $payload];
            $reports['create'][] = $this->reportRow('create', $row, null, 'new', 'Nieuwe prospect voor campaign '.$campaign->slug.'.', $payload);
        }

        $archiveQuery = OutreachProspect::query()
            ->where('outreach_campaign_id', $campaign->id)
            ->whereNull('archived_at');

        if ($csvProspectIds !== []) {
            $archiveQuery->whereNotIn('id', array_keys($csvProspectIds));
        }

        $archiveQuery->chunkById(250, function ($prospects) use (&$summary, &$reports, &$archives): void {
            foreach ($prospects as $prospect) {
                /** @var OutreachProspect $prospect */
                $summary['to_archive']++;
                $archives[] = ['prospect_id' => $prospect->id];
                $reports['archive'][] = $this->reportRow('archive', [], $prospect, 'campaign_missing_from_csv', 'Actieve prospect in gekozen campaign staat niet meer in CSV.');
            }
        });

        return [
            'summary' => $summary,
            'reports' => $reports,
            'updates' => $updates,
            'creates' => $creates,
            'archives' => $archives,
        ];
    }

    private function resolveCsvPath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $basePath = base_path($path);

        return is_file($basePath) ? $basePath : $path;
    }

    /**
     * @return list<array<string, string|int>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);

            return [];
        }

        $header = array_map(function ($value): string {
            return Str::lower(trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF"));
        }, $header);

        $rows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($data === [null]) {
                continue;
            }

            $combined = array_combine($header, array_pad($data, count($header), ''));

            if (! is_array($combined)) {
                continue;
            }

            $row = ['row_number' => $rowNumber];

            foreach (self::MASTER_FIELDS as $field) {
                $row[$field] = trim((string) ($combined[$field] ?? ''));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function changedMasterFields(OutreachProspect $prospect, array $row): array
    {
        $changes = [];

        foreach (self::MASTER_FIELDS as $field) {
            $incoming = trim((string) ($row[$field] ?? ''));

            if ($incoming === '') {
                continue;
            }

            if ((string) ($prospect->{$field} ?? '') !== $incoming) {
                $changes[$field] = $incoming;
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(OutreachCampaign $campaign, array $row): array
    {
        $payload = ['outreach_campaign_id' => $campaign->id];

        foreach (self::MASTER_FIELDS as $field) {
            $payload[$field] = trim((string) ($row[$field] ?? '')) ?: null;
        }

        if (blank($payload['company_name'])) {
            $payload['company_name'] = $payload['email'];
        }

        return $payload;
    }

    private function findByEmail(?string $email): ?OutreachProspect
    {
        if ($email === null) {
            return null;
        }

        return OutreachProspect::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->first();
    }

    private function findByWebsite(?string $domain): ?OutreachProspect
    {
        if ($domain === null) {
            return null;
        }

        return OutreachProspect::query()
            ->get()
            ->first(fn (OutreachProspect $prospect): bool => $this->normalizeWebsiteDomain($prospect->website) === $domain);
    }

    private function findByCompanyName(?string $companyName): ?OutreachProspect
    {
        if ($companyName === null) {
            return null;
        }

        return OutreachProspect::query()
            ->get()
            ->first(fn (OutreachProspect $prospect): bool => $this->normalizeCompanyName($prospect->company_name) === $companyName);
    }

    private function findFuzzyCompanyName(string $companyName, OutreachCampaign $campaign): ?OutreachProspect
    {
        $needle = $this->normalizeCompanyName($companyName);

        if ($needle === null || strlen($needle) < 8) {
            return null;
        }

        return OutreachProspect::query()
            ->where('outreach_campaign_id', $campaign->id)
            ->get()
            ->first(function (OutreachProspect $prospect) use ($needle): bool {
                $candidate = $this->normalizeCompanyName($prospect->company_name);

                if ($candidate === null || $candidate === $needle) {
                    return false;
                }

                similar_text($needle, $candidate, $percent);

                return $percent >= 92.0;
            });
    }

    private function normalizeEmail(?string $email): ?string
    {
        $value = Str::lower(trim((string) $email));

        return $value === '' ? null : $value;
    }

    private function normalizeWebsiteDomain(?string $website): ?string
    {
        $value = Str::lower(trim((string) $website));

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return rtrim($host, '.');
    }

    private function normalizeCompanyName(?string $companyName): ?string
    {
        $value = Str::lower(trim((string) $companyName));
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return $value === '' ? null : $value;
    }

    private function countCampaignProspectsWithMailHistory(OutreachCampaign $campaign): int
    {
        return OutreachProspect::query()
            ->where('outreach_campaign_id', $campaign->id)
            ->whereHas('emailLogs', fn ($query) => $query->whereIn('status', [
                OutreachEmailLog::STATUS_SENT,
                OutreachEmailLog::STATUS_FAILED,
                OutreachEmailLog::STATUS_SKIPPED,
            ]))
            ->count();
    }

    /**
     * @param  array<string, list<array<string, string>>>  $reports
     */
    private function writeReports(array $reports): void
    {
        $directory = storage_path('app/outreach-sync-reports');
        File::ensureDirectoryExists($directory);

        $files = [
            'create' => 'sync_to_create.csv',
            'update' => 'sync_to_update.csv',
            'review' => 'sync_to_review.csv',
            'archive' => 'sync_to_archive.csv',
            'skipped' => 'sync_skipped.csv',
        ];

        foreach ($files as $key => $filename) {
            $handle = fopen($directory.'/'.$filename, 'w');

            if ($handle === false) {
                continue;
            }

            fputcsv($handle, [
                'action',
                'row_number',
                'prospect_id',
                'match_type',
                'reason',
                'company_name',
                'email',
                'website',
                'existing_company_name',
                'existing_email',
                'existing_website',
                'changes',
            ]);

            foreach ($reports[$key] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }
    }

    /**
     * @return array<string, string>
     */
    private function reportRow(string $action, array $row, ?OutreachProspect $prospect, string $matchType, string $reason, array $changes = []): array
    {
        return [
            'action' => $action,
            'row_number' => (string) ($row['row_number'] ?? ''),
            'prospect_id' => (string) ($prospect?->id ?? ''),
            'match_type' => $matchType,
            'reason' => $reason,
            'company_name' => (string) ($row['company_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'website' => (string) ($row['website'] ?? ''),
            'existing_company_name' => (string) ($prospect?->company_name ?? ''),
            'existing_email' => (string) ($prospect?->email ?? ''),
            'existing_website' => (string) ($prospect?->website ?? ''),
            'changes' => $changes === [] ? '' : json_encode($changes, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function renderSummary(array $plan, bool $dryRun): void
    {
        $summary = $plan['summary'];

        $this->info($dryRun ? 'Outreach master sync rapport (dry-run)' : 'Outreach master sync rapport');
        $this->line('Totaal CSV: '.$summary['total_csv']);
        $this->line('Geldig met email: '.$summary['valid_with_email']);
        $this->line('Skipped zonder email: '.$summary['skipped_without_email']);
        $this->line('Matched by email: '.$summary['matched_by_email']);
        $this->line('Matched by website: '.$summary['matched_by_website']);
        $this->line('Matched by company: '.$summary['matched_by_company']);
        $this->line('Review conflicts: '.$summary['review_conflicts']);
        $this->line('Nieuw aan te maken: '.$summary['to_create']);
        $this->line('Te archiveren binnen campaign: '.$summary['to_archive']);
        $this->line('Updates: '.$summary['updates']);
        $this->line('Untouched: '.$summary['untouched']);
        $this->line('Errors: '.$summary['errors']);
        $this->line('Bestaande prospects met sent/failed/skipped logs behouden: '.$summary['existing_with_preserved_logs']);
        $this->line('CSV-rapporten: '.storage_path('app/outreach-sync-reports'));
    }
}
