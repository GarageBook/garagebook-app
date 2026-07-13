<?php

namespace App\Services\Growth\Motorclubs;

use App\Models\GrowthCampaign;
use App\Models\GrowthProspect;
use App\Models\OutreachEmailLog;
use App\Models\OutreachEvent;
use App\Models\OutreachProspect;
use App\Services\Growth\GrowthCampaignEligibilityService;
use App\Services\Growth\GrowthProspectNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MotorclubImportService
{
    private const REQUIRED_CAMPAIGNS = ['club2026', 'classic2026'];

    private const PERSONAL_EMAIL_DOMAINS = [
        'gmail.com',
        'googlemail.com',
        'hotmail.com',
        'outlook.com',
        'live.com',
        'msn.com',
        'icloud.com',
        'me.com',
        'mac.com',
        'yahoo.com',
        'yahoo.nl',
        'proton.me',
        'protonmail.com',
        'gmx.com',
        'gmx.net',
        'aol.com',
        'mail.com',
    ];

    public function __construct(
        private readonly GrowthProspectNormalizer $normalizer,
        private readonly GrowthCampaignEligibilityService $eligibility,
    ) {}

    /**
     * @return array<int, string>
     */
    public function requiredCampaigns(): array
    {
        return self::REQUIRED_CAMPAIGNS;
    }

    /**
     * @param  array{dry_run?:bool,limit?:int|null,campaign?:string|null,force?:bool,markdown_file?:string|null}  $options
     */
    public function import(string $file, array $options = []): MotorclubImportResult
    {
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $limit = $options['limit'] ?? null;
        $campaignFilter = $this->normalizer->clean($options['campaign'] ?? null);
        $force = (bool) ($options['force'] ?? false);
        $markdownFile = $options['markdown_file'] ?? base_path('docs/prospects/motorclubs.md');

        $result = new MotorclubImportResult;
        $campaigns = $this->campaigns($result);

        if ($result->errors !== []) {
            return $result;
        }

        if ($campaignFilter !== null && ! array_key_exists($campaignFilter, $campaigns)) {
            $result->addError('Onbekende of ontbrekende campagnefilter: '.$campaignFilter);

            return $result;
        }

        $csvRows = $this->readCsv($file);
        $markdownRows = is_file($markdownFile) ? $this->readMarkdown($markdownFile) : collect();
        $rows = $this->mergeRows($csvRows, $markdownRows, $result);
        $processed = 0;

        foreach ($rows as $row) {
            if ($limit !== null && $processed >= $limit) {
                break;
            }

            $normalized = $this->normalizeRow($row);

            if ($campaignFilter !== null && $normalized['campaign_slug'] !== $campaignFilter) {
                continue;
            }

            $processed++;
            $result->increment('read');
            $result->incrementCampaign($normalized['campaign_slug']);
            $result->incrementSubtype($normalized['prospect_subtype']);

            $assessment = $this->assess($normalized, $campaigns[$normalized['campaign_slug']] ?? null);
            $result->increment($assessment['email_bucket']);
            if (! blank($normalized['email'])) {
                $result->increment('email_present');
            }

            if ($assessment['valid']) {
                $result->increment('valid');
            }

            if ($assessment['status_bucket'] !== null) {
                $result->increment($assessment['status_bucket']);
            }

            $existing = $this->findExisting($normalized);
            $legacyMatches = $this->legacyMatches($normalized);
            $fuzzyMatches = $this->fuzzyMatches($normalized, $existing);
            $action = $this->actionFor($normalized, $assessment, $existing, $legacyMatches, $force);

            $result->increment($action);

            $record = [
                'name' => $normalized['name'],
                'website' => $normalized['website'],
                'email' => $normalized['email'],
                'campaign' => $normalized['campaign_slug'],
                'subtype' => $normalized['prospect_subtype'],
                'status' => $normalized['lifecycle_status'],
                'action' => $action,
                'reasons' => array_values(array_unique(array_merge(
                    $assessment['reasons'],
                    $legacyMatches !== [] ? ['legacy_outreach_match'] : [],
                    $fuzzyMatches !== [] ? ['possible_fuzzy_match'] : [],
                ))),
                'existing_id' => $existing?->id,
                'legacy_matches' => $legacyMatches,
                'fuzzy_matches' => $fuzzyMatches,
            ];

            $result->addRecord($record);

            if ($dryRun || ! in_array($action, ['create', 'updates'], true)) {
                continue;
            }

            if ($existing instanceof GrowthProspect) {
                $this->updateExisting($existing, $normalized, $campaigns[$normalized['campaign_slug']]);

                continue;
            }

            $this->createProspect($normalized, $campaigns[$normalized['campaign_slug']]);
        }

        return $result;
    }

    /**
     * @return array<string, GrowthCampaign>
     */
    private function campaigns(MotorclubImportResult $result): array
    {
        $campaigns = GrowthCampaign::query()
            ->whereIn('slug', self::REQUIRED_CAMPAIGNS)
            ->get()
            ->keyBy('slug');

        foreach (self::REQUIRED_CAMPAIGNS as $slug) {
            if (! $campaigns->has($slug)) {
                $result->addError('Vereiste growth campagne ontbreekt: '.$slug);
            }
        }

        return $campaigns->all();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function readCsv(string $path): Collection
    {
        if (! is_file($path)) {
            return collect();
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return collect();
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header): string => trim((string) $header), $headers);
        $rows = collect();

        while (($values = fgetcsv($handle)) !== false) {
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            if (array_filter($row) !== []) {
                $rows->push($row + ['_source' => 'csv']);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return Collection<string, array<string, string>>
     */
    private function readMarkdown(string $path): Collection
    {
        $rows = collect();
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $headers = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (! str_starts_with($line, '|')) {
                continue;
            }

            $columns = array_map('trim', explode('|', trim($line, '|')));

            if ($columns === [] || ($columns[0] ?? '') === '---') {
                continue;
            }

            if ($headers === []) {
                $headers = $columns;

                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = $columns[$index] ?? '';
            }

            $key = $this->rowKey($row['Naam'] ?? '', $row['Website'] ?? '');
            $rows->put($key, $row + ['_source' => 'markdown']);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, string>>  $csvRows
     * @param  Collection<string, array<string, string>>  $markdownRows
     * @return Collection<int, array<string, string>>
     */
    private function mergeRows(Collection $csvRows, Collection $markdownRows, MotorclubImportResult $result): Collection
    {
        return $csvRows->map(function (array $csvRow) use ($markdownRows, $result): array {
            $key = $this->rowKey($csvRow['name'] ?? '', $csvRow['website'] ?? '');
            $markdown = $markdownRows->get($key);

            if ($markdown === null) {
                $result->addSourceInconsistency($csvRow['name'] ?? '(unknown)', 'markdown_row', 'missing matching row', '');

                return $csvRow;
            }

            $this->compareSourceValue($result, $csvRow, $markdown, 'website', 'Website');
            $this->compareSourceValue($result, $csvRow, $markdown, 'email', 'E-mailadres');
            $this->compareSourceValue($result, $csvRow, $markdown, 'priority', 'Prioriteit (A/B/C)');
            $this->compareSourceValue($result, $csvRow, $markdown, 'warmth', 'Warmte (Warm/Lauw/Koud)');
            $this->compareSourceValue($result, $csvRow, $markdown, 'status', 'Status');

            return $csvRow + [
                'markdown_category' => $markdown['Categorie'] ?? '',
                'markdown_subcategory' => $markdown['Subcategorie'] ?? '',
                'markdown_estimated_reach' => $markdown['Geschat bereik'] ?? '',
                'markdown_newsletter_status' => $markdown['Nieuwsbrief (ja/nee/onbekend)'] ?? '',
                'markdown_primary_contact_channel' => $markdown['Primair contactkanaal'] ?? '',
                'markdown_organizes_events' => $markdown['Organiseert evenementen? (ja/nee/onbekend)'] ?? '',
                'markdown_has_magazine' => $markdown['Eigen magazine? (ja/nee/onbekend)'] ?? '',
                'markdown_campaign' => $markdown['Campagne'] ?? '',
                'markdown_why_interesting' => $markdown['Waarom interessant'] ?? '',
                'markdown_approach_strategy' => $markdown['Benaderstrategie'] ?? '',
                'markdown_status' => $markdown['Status'] ?? '',
                'markdown_notes' => $markdown['Opmerkingen'] ?? '',
            ];
        });
    }

    /**
     * @param  array<string, string>  $csvRow
     * @param  array<string, string>  $markdown
     */
    private function compareSourceValue(MotorclubImportResult $result, array $csvRow, array $markdown, string $csvField, string $markdownField): void
    {
        $csvValue = $this->emptyMarkerToNull($csvRow[$csvField] ?? null);
        $markdownValue = $this->emptyMarkerToNull($markdown[$markdownField] ?? null);

        if ($csvField === 'website') {
            $csvValue = $this->normalizer->normalizeWebsite($csvValue);
            $markdownValue = $this->normalizer->normalizeWebsite($markdownValue);
        }

        if ($csvField === 'email') {
            $csvValue = $this->normalizer->normalizeEmail($csvValue);
            $markdownValue = $this->normalizer->normalizeEmail($markdownValue);
        }
        if ($csvField === 'status') {
            $csvValue = $this->normalizeSourceStatus($csvValue);
            $markdownValue = $this->normalizeSourceStatus($markdownValue);
        }

        if (($csvValue ?? '') !== ($markdownValue ?? '')) {
            $result->addSourceInconsistency($csvRow['name'] ?? '(unknown)', $csvField, (string) ($csvValue ?? ''), (string) ($markdownValue ?? ''));
        }
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $name = $this->normalizer->clean($row['name'] ?? null);
        $website = $this->normalizer->normalizeWebsite($row['website'] ?? null);
        $email = $this->normalizer->normalizeEmail($this->emptyMarkerToNull($row['email'] ?? null));
        $domain = $this->normalizer->normalizeDomain($website);
        $category = $this->normalizer->clean($row['markdown_category'] ?? $row['category'] ?? null);
        $subcategory = $this->normalizer->clean($row['markdown_subcategory'] ?? null);
        $campaignSlug = $this->campaignSlug($row, $category, $subcategory);
        $subtype = $this->prospectSubtype($row, $category, $subcategory, $campaignSlug, $email);
        $emailStatus = $email === null
            ? GrowthProspect::EMAIL_STATUS_MISSING
            : (filter_var($email, FILTER_VALIDATE_EMAIL) === false ? GrowthProspect::EMAIL_STATUS_INVALID : GrowthProspect::EMAIL_STATUS_FOUND);
        $personalEmail = $this->isPersonalEmail($email);
        $manualReview = $email === null || $emailStatus === GrowthProspect::EMAIL_STATUS_INVALID || $personalEmail || blank($website);
        $lifecycleStatus = $manualReview ? GrowthProspect::LIFECYCLE_MANUAL_REVIEW : GrowthProspect::LIFECYCLE_ENRICHED;
        $notes = $this->notes($row);

        return [
            'name' => $name,
            'website' => $website,
            'organization_key' => $this->normalizer->organizationKey($name, $domain),
            'normalized_domain' => $domain,
            'category' => $category ?: 'Motorclub',
            'subcategory' => $subcategory,
            'prospect_type' => 'community',
            'prospect_subtype' => $subtype,
            'region' => $this->normalizer->clean($row['region'] ?? null),
            'estimated_reach' => $this->emptyMarkerToNull($row['markdown_estimated_reach'] ?? null) ?: 'unknown',
            'newsletter_status' => $this->newsletterStatus($row['markdown_newsletter_status'] ?? null),
            'primary_contact_channel' => $this->normalizer->clean($row['markdown_primary_contact_channel'] ?? null),
            'contact_name' => $this->emptyMarkerToNull($row['contact_name'] ?? null),
            'email' => $email,
            'normalized_email' => $email,
            'email_status' => $emailStatus,
            'verification_required' => $manualReview,
            'priority' => $this->normalizer->clean($row['priority'] ?? null),
            'warmth' => $this->normalizer->clean($row['warmth'] ?? null),
            'score' => is_numeric($row['score'] ?? null) ? (int) $row['score'] : null,
            'status' => $lifecycleStatus,
            'lifecycle_status' => $lifecycleStatus,
            'campaign_slug' => $campaignSlug,
            'partner_slug' => $this->normalizer->clean($row['partner_slug'] ?? null) ?: Str::slug((string) $name),
            'source_url' => $website,
            'source_type' => 'docs_motorclubs',
            'quality_score' => $manualReview ? 60 : 85,
            'quality_flags' => $this->qualityFlags($email, $emailStatus, $personalEmail, $website),
            'quality_verdict' => $manualReview ? 'manual_review' : 'manual_review',
            'quality_reason' => $manualReview ? 'manual review before first outreach' : 'validated source, not auto-ready',
            'skip_reason' => $this->skipReason($email, $emailStatus, $personalEmail, $website),
            'notes' => $notes,
            'why_interesting' => $this->normalizer->clean($row['markdown_why_interesting'] ?? null),
            'approach_strategy' => $this->normalizer->clean($row['markdown_approach_strategy'] ?? null),
            'outreach_status_source' => $this->normalizer->clean($row['markdown_status'] ?? $row['status'] ?? null),
            'has_personal_email' => $personalEmail,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{valid:bool,reasons:array<int,string>,email_bucket:string,status_bucket:?string}
     */
    private function assess(array $row, ?GrowthCampaign $campaign): array
    {
        $reasons = [];

        if ($campaign === null) {
            $reasons[] = 'missing_campaign';
        }

        if (blank($row['name'])) {
            $reasons[] = 'missing_name';
        }

        if (blank($row['website']) && blank($row['email'])) {
            $reasons[] = 'missing_website_and_email';
        }

        if ($row['email_status'] === GrowthProspect::EMAIL_STATUS_INVALID) {
            $reasons[] = 'invalid_email';
        }

        if ($row['has_personal_email']) {
            $reasons[] = GrowthCampaignEligibilityService::REASON_PERSONAL_EMAIL;
        }

        if ($row['email_status'] === GrowthProspect::EMAIL_STATUS_MISSING) {
            $reasons[] = GrowthCampaignEligibilityService::REASON_MISSING_EMAIL;
        }

        $emailBucket = match (true) {
            $row['has_personal_email'] => 'personal_email',
            $row['email_status'] === GrowthProspect::EMAIL_STATUS_MISSING => 'missing_email',
            default => 'public_email',
        };

        $statusBucket = null;
        if (in_array('missing_website_and_email', $reasons, true) || in_array('missing_campaign', $reasons, true) || in_array('missing_name', $reasons, true)) {
            $statusBucket = 'invalid';
        } elseif ($row['lifecycle_status'] === GrowthProspect::LIFECYCLE_MANUAL_REVIEW) {
            $statusBucket = 'manual_review';
        }

        return [
            'valid' => ! in_array('missing_campaign', $reasons, true) && ! in_array('missing_name', $reasons, true),
            'reasons' => $reasons,
            'email_bucket' => $emailBucket,
            'status_bucket' => $statusBucket,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function findExisting(array $row): ?GrowthProspect
    {
        return GrowthProspect::query()
            ->where(function ($query) use ($row): void {
                if (filled($row['normalized_email'])) {
                    $query->orWhere('normalized_email', $row['normalized_email'])
                        ->orWhere('email', $row['normalized_email']);
                }

                if (filled($row['normalized_domain'])) {
                    $query->orWhere('normalized_domain', $row['normalized_domain'])
                        ->orWhere('website', 'like', '%'.$row['normalized_domain'].'%');
                }

                if (filled($row['organization_key'])) {
                    $query->orWhere('organization_key', $row['organization_key']);
                }

                if (filled($row['partner_slug'])) {
                    $query->orWhere('partner_slug', $row['partner_slug']);
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function legacyMatches(array $row): array
    {
        $matches = [];

        $prospectQuery = OutreachProspect::query();
        $hasProspectIdentifier = false;

        if (filled($row['normalized_email'])) {
            $hasProspectIdentifier = true;
            $prospectQuery->orWhere('email', $row['normalized_email']);
        }

        if (filled($row['normalized_domain'])) {
            $hasProspectIdentifier = true;
            $prospectQuery->orWhere('website', 'like', '%'.$row['normalized_domain'].'%');
        }

        if ($hasProspectIdentifier && $prospectQuery->exists()) {
            $matches[] = 'legacy_outreach_prospect';
        }

        if (filled($row['normalized_email']) && OutreachEmailLog::query()->where('to_email', $row['normalized_email'])->exists()) {
            $matches[] = 'legacy_outreach_email_log';
        }

        if (filled($row['normalized_domain']) && OutreachEvent::query()
            ->whereHas('prospect', fn ($query) => $query->where('website', 'like', '%'.$row['normalized_domain'].'%'))
            ->exists()) {
            $matches[] = 'legacy_outreach_event';
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function fuzzyMatches(array $row, ?GrowthProspect $existing): array
    {
        if ($existing instanceof GrowthProspect || blank($row['name'])) {
            return [];
        }

        $slug = Str::slug((string) $row['name']);
        $shortSlug = Str::before($slug, '-nederland');

        if (blank($shortSlug) || mb_strlen($shortSlug) < 5) {
            return [];
        }

        return GrowthProspect::query()
            ->where('name', 'like', '%'.str_replace('-', '%', $shortSlug).'%')
            ->limit(5)
            ->pluck('name')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{valid:bool,reasons:array<int,string>,email_bucket:string,status_bucket:?string}  $assessment
     * @param  array<int, string>  $legacyMatches
     */
    private function actionFor(array $row, array $assessment, ?GrowthProspect $existing, array $legacyMatches, bool $force): string
    {
        if (! $assessment['valid']) {
            return 'excluded';
        }

        if ($existing instanceof GrowthProspect) {
            if (! $force) {
                return 'existing';
            }

            return 'updates';
        }

        if ($legacyMatches !== []) {
            return 'duplicates';
        }

        if ($assessment['status_bucket'] === 'invalid') {
            return 'invalid';
        }

        return 'create';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createProspect(array $row, GrowthCampaign $campaign): GrowthProspect
    {
        return GrowthProspect::query()->create($this->payload($row, $campaign));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function updateExisting(GrowthProspect $prospect, array $row, GrowthCampaign $campaign): void
    {
        $payload = $this->payload($row, $campaign);

        foreach ($payload as $field => $value) {
            if (in_array($field, ['last_contacted_at', 'next_follow_up_at'], true)) {
                continue;
            }

            if (filled($prospect->{$field}) && ! in_array($field, [
                'campaign_id',
                'last_campaign_id',
                'last_campaign_slug',
                'skip_reason',
                'quality_score',
                'quality_flags',
                'quality_verdict',
                'quality_reason',
                'notes',
            ], true)) {
                unset($payload[$field]);
            }
        }

        $prospect->fill($payload)->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function payload(array $row, GrowthCampaign $campaign): array
    {
        return [
            'name' => $row['name'],
            'website' => $row['website'],
            'organization_key' => $row['organization_key'],
            'normalized_domain' => $row['normalized_domain'],
            'category' => $row['category'],
            'subcategory' => $row['subcategory'],
            'prospect_type' => 'community',
            'prospect_subtype' => $row['prospect_subtype'],
            'region' => $row['region'],
            'estimated_reach' => $row['estimated_reach'],
            'newsletter_status' => $row['newsletter_status'],
            'primary_contact_channel' => $row['primary_contact_channel'],
            'contact_name' => $row['contact_name'],
            'email' => $row['email'],
            'normalized_email' => $row['normalized_email'],
            'email_status' => $row['email_status'],
            'verification_required' => $row['verification_required'],
            'priority' => $row['priority'],
            'warmth' => $row['warmth'],
            'score' => $row['score'],
            'status' => $row['status'],
            'lifecycle_status' => $row['lifecycle_status'],
            'campaign_id' => $campaign->id,
            'last_campaign_id' => null,
            'last_campaign_slug' => $campaign->slug,
            'partner_slug' => $row['partner_slug'],
            'source_url' => $row['source_url'],
            'source_type' => $row['source_type'],
            'quality_score' => $row['quality_score'],
            'quality_flags' => $row['quality_flags'],
            'quality_verdict' => $row['quality_verdict'],
            'quality_reason' => $row['quality_reason'],
            'skip_reason' => $row['skip_reason'],
            'notes' => $row['notes'],
            'why_interesting' => $row['why_interesting'],
            'approach_strategy' => $row['approach_strategy'],
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function campaignSlug(array $row, ?string $category, ?string $subcategory): string
    {
        $campaign = Str::lower((string) ($row['campaign'] ?? $row['markdown_campaign'] ?? ''));

        if (in_array($campaign, ['club2026', 'classic2026'], true)) {
            return $campaign;
        }

        if (Str::contains($campaign, 'classic')) {
            return 'classic2026';
        }

        $haystack = Str::lower(($category ?? '').' '.($subcategory ?? '').' '.($row['name'] ?? ''));

        return Str::contains($haystack, ['klassiek', 'classic', 'veteraan', 'oldtimer'])
            ? 'classic2026'
            : 'club2026';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function prospectSubtype(array $row, ?string $category, ?string $subcategory, string $campaignSlug, ?string $email): string
    {
        $haystack = Str::lower(($category ?? '').' '.($subcategory ?? '').' '.($row['name'] ?? ''));

        if ($campaignSlug === 'classic2026' || Str::contains($haystack, ['klassiek', 'classic', 'veteraan', 'oldtimer'])) {
            return 'oldtimer_club';
        }

        if (Str::contains($haystack, ['forum']) && $email !== null) {
            return 'forum';
        }

        if (Str::contains($haystack, ['merkclub', 'modelclub', 'modelcommunity', 'merk/modelclub'])) {
            return 'brand_club';
        }

        return 'motorcycle_club';
    }

    private function newsletterStatus(mixed $value): ?string
    {
        $value = Str::lower((string) $this->emptyMarkerToNull($value));

        return match ($value) {
            'ja' => 'yes',
            'nee' => 'no',
            'onbekend', '' => 'unknown',
            default => $value,
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    private function notes(array $row): ?string
    {
        $parts = array_filter([
            $this->normalizer->clean($row['notes'] ?? null),
            $this->normalizer->clean($row['markdown_notes'] ?? null),
            $this->normalizer->clean($row['markdown_status'] ?? null) ? 'Outreachstatus bron: '.$this->normalizer->clean($row['markdown_status'] ?? null) : null,
        ]);

        return $parts === [] ? null : implode(PHP_EOL.PHP_EOL, $parts);
    }

    /**
     * @return array<int, string>
     */
    private function qualityFlags(?string $email, string $emailStatus, bool $personalEmail, ?string $website): array
    {
        $flags = ['motorclub_source', 'manual_review_before_outreach'];

        if ($email !== null && ! $personalEmail && $emailStatus === GrowthProspect::EMAIL_STATUS_FOUND) {
            $flags[] = 'public_email';
        }

        if ($emailStatus === GrowthProspect::EMAIL_STATUS_MISSING) {
            $flags[] = 'missing_email';
        }

        if ($emailStatus === GrowthProspect::EMAIL_STATUS_INVALID) {
            $flags[] = 'invalid_email';
        }

        if ($personalEmail) {
            $flags[] = 'personal_email';
        }

        if (blank($website)) {
            $flags[] = 'missing_website';
        }

        return $flags;
    }

    private function skipReason(?string $email, string $emailStatus, bool $personalEmail, ?string $website): ?string
    {
        if (blank($website) && $email === null) {
            return 'manual_review_required';
        }

        if ($personalEmail) {
            return GrowthCampaignEligibilityService::REASON_PERSONAL_EMAIL;
        }

        return match ($emailStatus) {
            GrowthProspect::EMAIL_STATUS_MISSING => GrowthCampaignEligibilityService::REASON_MISSING_EMAIL,
            GrowthProspect::EMAIL_STATUS_INVALID => GrowthCampaignEligibilityService::REASON_INVALID_EMAIL,
            default => null,
        };
    }

    private function isPersonalEmail(?string $email): bool
    {
        if ($email === null || ! str_contains($email, '@')) {
            return false;
        }

        $domain = Str::lower((string) Str::after($email, '@'));

        return in_array($domain, self::PERSONAL_EMAIL_DOMAINS, true);
    }

    private function emptyMarkerToNull(mixed $value): ?string
    {
        $value = $this->normalizer->clean($value);

        if ($value === null) {
            return null;
        }

        return Str::lower($value) === 'onbekend' ? null : $value;
    }

    private function rowKey(string $name, string $website): string
    {
        $domain = $this->normalizer->normalizeDomain($website);

        return $domain ?: Str::slug(Str::lower($name));
    }

    private function normalizeSourceStatus(?string $status): ?string
    {
        $status = $this->normalizer->clean($status);

        if ($status === null) {
            return null;
        }

        return match (Str::lower($status)) {
            'new', 'nog niet benaderd' => 'not_contacted',
            default => Str::lower($status),
        };
    }
}
