<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthOutreachEvent;
use App\Models\GrowthProspect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Community2026ImportService
{
    public const CAMPAIGN_SLUG = 'community2026';

    private const COLUMNS = [
        'name', 'website', 'email', 'phone', 'city', 'source_url', 'source_type', 'prospect_type', 'prospect_subtype', 'notes',
    ];

    public function __construct(
        private readonly GrowthProspectNormalizer $normalizer,
        private readonly GrowthOutreachEventLogger $events,
    ) {}

    /**
     * @return array{created:int, updated:int, skipped:int, enriched:int, imported:int}
     */
    public function importPath(string $path): array
    {
        $rows = $this->readRows($path);
        $campaign = $this->campaign();
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'enriched' => 0, 'imported' => 0];

        foreach ($rows as $row) {
            $payload = $this->normalizer->normalizePayload($row);

            if (blank($payload['name'] ?? null)) {
                $result['skipped']++;

                continue;
            }

            $prospect = $this->findExisting($payload);
            $eventType = GrowthOutreachEvent::TYPE_IMPORTED;

            if ($prospect instanceof GrowthProspect) {
                $prospect->fill($this->mergePayload($prospect, $payload));
                $prospect->campaign_id = $prospect->campaign_id ?: $campaign->id;
                $prospect->save();
                $result['updated']++;
                $eventType = GrowthOutreachEvent::TYPE_ENRICHED;
            } else {
                $prospect = GrowthProspect::query()->create($payload + [
                    'campaign_id' => $campaign->id,
                    'partner_slug' => $this->uniquePartnerSlug((string) $payload['name']),
                ]);
                $result['created']++;
            }

            $this->events->log($prospect, GrowthOutreachEvent::TYPE_IMPORTED, $campaign, self::CAMPAIGN_SLUG, null, ['source' => $row]);
            if ($eventType === GrowthOutreachEvent::TYPE_ENRICHED) {
                $this->events->log($prospect, GrowthOutreachEvent::TYPE_ENRICHED, $campaign, self::CAMPAIGN_SLUG, null, ['source' => $row]);
                $result['enriched']++;
            }
            $result['imported']++;
        }

        return $result;
    }

    public function campaign(): GrowthCampaign
    {
        return GrowthCampaign::query()->updateOrCreate(
            ['slug' => self::CAMPAIGN_SLUG],
            [
                'name' => 'Community2026',
                'description' => 'Merkclubs, oldtimerclubs, camperclubs, youngtimerclubs en andere voertuigcommunities.',
                'status' => GrowthCampaign::STATUS_DRAFT,
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? array_values(Arr::isAssoc($decoded) ? [$decoded] : $decoded) : [];
        }

        return $this->readCsv($path);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header): string => preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header)) ?: '', $headers);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null]) {
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                if (! in_array($header, self::COLUMNS, true)) {
                    continue;
                }

                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            if (array_filter($row) !== []) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findExisting(array $payload): ?GrowthProspect
    {
        $hasIdentifier = collect(['normalized_email', 'normalized_domain', 'organization_key', 'phone'])
            ->contains(fn (string $field): bool => filled($payload[$field] ?? null));

        if (! $hasIdentifier) {
            return null;
        }

        return GrowthProspect::query()
            ->where(function (Builder $query) use ($payload): void {
                foreach (['normalized_email', 'normalized_domain', 'organization_key', 'phone'] as $field) {
                    if (filled($payload[$field] ?? null)) {
                        $query->orWhere($field, $payload[$field]);
                    }
                }
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergePayload(GrowthProspect $prospect, array $payload): array
    {
        foreach ($payload as $field => $value) {
            if (blank($value)) {
                unset($payload[$field]);

                continue;
            }

            if (filled($prospect->{$field}) && ! in_array($field, ['email_status', 'verification_required', 'lifecycle_status', 'status', 'notes'], true)) {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    private function uniquePartnerSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'community-prospect';
        $slug = $base;
        $suffix = 2;

        while (GrowthProspect::query()->where('partner_slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
