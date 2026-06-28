<?php

namespace App\Services\Growth;

use App\Models\GrowthProspect;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GrowthProspectCsvImportService
{
    public const MAPPING_FIELDS = [
        'name' => 'Naam',
        'website' => 'Website',
        'category' => 'Categorie',
        'email' => 'E-mail',
        'contact_name' => 'Contactpersoon',
        'region' => 'Regio',
        'priority' => 'Prioriteit',
        'warmth' => 'Warmte',
        'score' => 'Score',
        'status' => 'Status',
        'notes' => 'Notities',
        'partner_slug' => 'Partner slug',
    ];

    private const DUPLICATE_FIELDS = [
        'website',
        'email',
        'partner_slug',
    ];

    public function parsePath(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [
                'headers' => [],
                'rows' => [],
            ];
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header): string => $this->cleanValue((string) $header), $headers);

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $source = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $source[$header] = $this->cleanValue((string) ($values[$index] ?? ''));
            }

            $rows[] = [
                'line' => $line,
                'source' => $source,
            ];
        }

        fclose($handle);

        return [
            'headers' => array_values(array_filter($headers, fn (string $header): bool => $header !== '')),
            'rows' => $rows,
        ];
    }

    public function defaultMapping(array $headers): array
    {
        $normalizedHeaders = collect($headers)->mapWithKeys(fn (string $header): array => [
            $this->normalizeHeader($header) => $header,
        ]);

        return collect(self::MAPPING_FIELDS)
            ->mapWithKeys(function (string $label, string $field) use ($normalizedHeaders): array {
                return [$field => $normalizedHeaders->get($this->normalizeHeader($field), '')];
            })
            ->all();
    }

    public function analyze(array $parsed, array $mapping): array
    {
        $items = [];
        $summary = [
            'new' => 0,
            'update' => 0,
            'skipped' => 0,
        ];
        $seenNewIdentifiers = [];
        $seenUpdateIds = [];

        foreach ($parsed['rows'] ?? [] as $row) {
            $data = $this->mappedData($row['source'] ?? [], $mapping);
            $existingIds = $this->matchingExistingIds($data);
            $action = 'new';
            $reason = null;
            $existingId = null;

            if (blank($data['name'] ?? null)) {
                $action = 'skipped';
                $reason = 'Naam ontbreekt';
            } elseif ($existingIds->count() > 1) {
                $action = 'skipped';
                $reason = 'Meerdere bestaande prospects matchen';
            } elseif ($existingIds->count() === 1) {
                $action = 'update';
                $existingId = (int) $existingIds->first();

                if (isset($seenUpdateIds[$existingId])) {
                    $action = 'skipped';
                    $reason = 'Dubbele rij voor dezelfde bestaande prospect';
                } else {
                    $seenUpdateIds[$existingId] = true;
                }
            } else {
                $duplicateKey = $this->firstSeenIdentifier($data, $seenNewIdentifiers);

                if ($duplicateKey !== null) {
                    $action = 'skipped';
                    $reason = 'Dubbele rij in CSV op '.$duplicateKey;
                } else {
                    foreach ($this->identifierKeys($data) as $key) {
                        $seenNewIdentifiers[$key] = true;
                    }
                }
            }

            $summary[$action]++;
            $items[] = [
                'line' => (int) ($row['line'] ?? 0),
                'action' => $action,
                'reason' => $reason,
                'existing_id' => $existingId,
                'data' => $data,
                'source' => $row['source'] ?? [],
            ];
        }

        return [
            'summary' => $summary,
            'items' => $items,
            'preview' => array_slice($items, 0, 20),
        ];
    }

    public function import(array $parsed, array $mapping): array
    {
        $analysis = $this->analyze($parsed, $mapping);
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($analysis['items'] as $item) {
            if ($item['action'] === 'skipped') {
                $result['skipped']++;

                continue;
            }

            if ($item['action'] === 'update') {
                GrowthProspect::query()
                    ->whereKey($item['existing_id'])
                    ->update($item['data']);
                $result['updated']++;

                continue;
            }

            GrowthProspect::query()->create($item['data']);
            $result['created']++;
        }

        return [
            ...$result,
            'summary' => $analysis['summary'],
        ];
    }

    private function mappedData(array $source, array $mapping): array
    {
        $data = [];

        foreach (array_keys(self::MAPPING_FIELDS) as $field) {
            $header = Arr::get($mapping, $field);

            if (! is_string($header) || $header === '' || ! array_key_exists($header, $source)) {
                continue;
            }

            $value = $this->cleanValue((string) $source[$header]);
            $data[$field] = $value === '' ? null : $value;
        }

        if (array_key_exists('score', $data) && $data['score'] !== null) {
            $data['score'] = max(0, min(255, (int) $data['score']));
        }

        return $data;
    }

    private function matchingExistingIds(array $data): Collection
    {
        $identifiers = collect(self::DUPLICATE_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $data[$field] ?? null])
            ->filter(fn ($value): bool => filled($value));

        if ($identifiers->isEmpty()) {
            return collect();
        }

        return GrowthProspect::query()
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $field => $value) {
                    $query->orWhere($field, $value);
                }
            })
            ->pluck('id')
            ->unique()
            ->values();
    }

    private function firstSeenIdentifier(array $data, array $seen): ?string
    {
        foreach ($this->identifierKeys($data) as $key) {
            if (isset($seen[$key])) {
                return Str::before($key, ':');
            }
        }

        return null;
    }

    private function identifierKeys(array $data): array
    {
        return collect(self::DUPLICATE_FIELDS)
            ->map(fn (string $field): ?string => filled($data[$field] ?? null) ? $field.':'.Str::lower((string) $data[$field]) : null)
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->lower()
            ->replace(['-', ' '], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();
    }

    private function cleanValue(string $value): string
    {
        return trim($value);
    }

    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn ($value): bool => trim((string) $value) === '');
    }
}
