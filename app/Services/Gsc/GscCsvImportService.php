<?php

namespace App\Services\Gsc;

use App\Models\GscCountrySnapshot;
use App\Models\GscDateSnapshot;
use App\Models\GscDeviceSnapshot;
use App\Models\GscImportLog;
use App\Models\GscImportSession;
use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use App\Models\GscSearchAppearanceSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SplFileObject;
use Throwable;

class GscCsvImportService
{
    public function __construct(
        private readonly GscPageTypeDetector $pageTypeDetector,
        private readonly GscCsvTypeDetector $typeDetector,
    ) {}

    /**
     * @return array{pages:int,queries:int,date:string}
     */
    public function import(?string $pagesPath, ?string $queriesPath, string $date, bool $replaceExisting = false): array
    {
        $snapshotDate = Carbon::parse($date)->toDateString();

        if ($replaceExisting) {
            GscPageSnapshot::query()->whereDate('date', $snapshotDate)->delete();
            GscQuerySnapshot::query()->whereDate('date', $snapshotDate)->delete();
        }

        return [
            'pages' => $pagesPath ? $this->importPages($pagesPath, $snapshotDate) : 0,
            'queries' => $queriesPath ? $this->importQueries($queriesPath, $snapshotDate) : 0,
            'date' => $snapshotDate,
        ];
    }

    /**
     * @param  array<int, string|array{path:string,name?:string,delete_after?:bool}>  $files
     * @return array<string, mixed>
     */
    public function importBulkSession(array $files, Carbon|string $date, bool $replace = false, ?int $userId = null): array
    {
        $snapshotDate = Carbon::parse($date)->toDateString();
        $startedAt = microtime(true);
        $normalizedFiles = $this->normalizeFiles($files);

        $session = GscImportSession::query()->create([
            'import_date' => $snapshotDate,
            'user_id' => $userId,
            'status' => 'pending',
            'total_files' => count($normalizedFiles),
            'warnings' => [],
            'errors' => [],
        ]);

        $warnings = [];
        $errors = [];
        $counts = [
            'pages' => 0,
            'queries' => 0,
            'countries' => 0,
            'devices' => 0,
            'search_appearances' => 0,
            'date_rows' => 0,
            'processed_files' => 0,
            'skipped_files' => 0,
        ];

        try {
            if (! $replace && $this->hasSnapshotsForDate($snapshotDate)) {
                $message = 'Er bestaat al GSC-data voor deze datum. Kies bestaande data vervangen om opnieuw te importeren.';
                $warnings[] = $message;
                $counts['skipped_files'] = count($normalizedFiles);

                return $this->finishSession($session, $startedAt, 'failed', $counts, $warnings, [$message]);
            }

            if ($replace) {
                $this->deleteSnapshotsForDate($snapshotDate);
                $warnings[] = 'Bestaande GSC-data voor '.$snapshotDate.' is vervangen.';
            }

            foreach ($normalizedFiles as $file) {
                $type = $this->typeDetector->detect($file['path'], $file['name']);

                try {
                    $imported = match ($type) {
                        GscCsvTypeDetector::PAGES => $this->importPages($file['path'], $snapshotDate),
                        GscCsvTypeDetector::QUERIES => $this->importQueries($file['path'], $snapshotDate),
                        GscCsvTypeDetector::COUNTRIES => $this->importCountries($file['path'], $snapshotDate),
                        GscCsvTypeDetector::DEVICES => $this->importDevices($file['path'], $snapshotDate),
                        GscCsvTypeDetector::SEARCH_APPEARANCE => $this->importSearchAppearances($file['path'], $snapshotDate),
                        GscCsvTypeDetector::DATES => $this->importDates($file['path'], $snapshotDate),
                        GscCsvTypeDetector::FILTERS, GscCsvTypeDetector::UNKNOWN => null,
                        default => null,
                    };
                } catch (Throwable $exception) {
                    $errors[] = $file['name'].': '.$exception->getMessage();
                    $counts['skipped_files']++;

                    continue;
                }

                if ($imported === null) {
                    $warnings[] = $file['name'].': overgeslagen ('.$type.').';
                    $counts['skipped_files']++;

                    continue;
                }

                match ($type) {
                    GscCsvTypeDetector::PAGES => $counts['pages'] += $imported,
                    GscCsvTypeDetector::QUERIES => $counts['queries'] += $imported,
                    GscCsvTypeDetector::COUNTRIES => $counts['countries'] += $imported,
                    GscCsvTypeDetector::DEVICES => $counts['devices'] += $imported,
                    GscCsvTypeDetector::SEARCH_APPEARANCE => $counts['search_appearances'] += $imported,
                    GscCsvTypeDetector::DATES => $counts['date_rows'] += $imported,
                    default => null,
                };

                $counts['processed_files']++;
            }

            $status = $errors !== []
                ? ($counts['processed_files'] > 0 ? 'completed_with_warnings' : 'failed')
                : ($warnings !== [] ? 'completed_with_warnings' : 'completed');

            return $this->finishSession($session, $startedAt, $status, $counts, $warnings, $errors);
        } finally {
            foreach ($normalizedFiles as $file) {
                if ($file['delete_after'] && is_file($file['path'])) {
                    @unlink($file['path']);
                }
            }
        }
    }

    public function hasSnapshotsForDate(string $date): bool
    {
        $date = Carbon::parse($date)->toDateString();

        return GscPageSnapshot::query()->whereDate('date', $date)->exists()
            || GscQuerySnapshot::query()->whereDate('date', $date)->exists()
            || GscCountrySnapshot::query()->whereDate('date', $date)->exists()
            || GscDeviceSnapshot::query()->whereDate('date', $date)->exists()
            || GscSearchAppearanceSnapshot::query()->whereDate('date', $date)->exists()
            || GscDateSnapshot::query()->whereDate('date', $date)->exists();
    }

    public function deleteSnapshotsForDate(string $date): void
    {
        $date = Carbon::parse($date)->toDateString();

        GscPageSnapshot::query()->whereDate('date', $date)->delete();
        GscQuerySnapshot::query()->whereDate('date', $date)->delete();
        GscCountrySnapshot::query()->whereDate('date', $date)->delete();
        GscDeviceSnapshot::query()->whereDate('date', $date)->delete();
        GscSearchAppearanceSnapshot::query()->whereDate('date', $date)->delete();
        GscDateSnapshot::query()->whereDate('date', $date)->delete();
    }

    private function importPages(string $path, string $date): int
    {
        $count = 0;

        foreach ($this->readRows($path) as $row) {
            $pageUrl = $this->value($row, ['Pagina', 'Page']);
            $pagePath = $this->pageTypeDetector->pathFromUrl($pageUrl);

            if ($pagePath === null) {
                continue;
            }

            GscPageSnapshot::query()->updateOrCreate(
                ['date' => $date, 'path' => $pagePath],
                $this->metricPayload($row) + [
                    'page_url' => $pageUrl,
                    'page_type' => $this->pageTypeDetector->detect($pagePath),
                ],
            );

            $count++;
        }

        return $count;
    }

    private function importQueries(string $path, string $date): int
    {
        $count = 0;

        foreach ($this->readRows($path) as $row) {
            $query = $this->value($row, ['Zoekopdracht', 'Query']);
            $pageUrl = $this->value($row, ['Pagina', 'Page'], false);
            $pagePath = $this->pageTypeDetector->pathFromUrl($pageUrl);

            if ($query === '') {
                continue;
            }

            GscQuerySnapshot::query()->updateOrCreate(
                ['date' => $date, 'query' => $query, 'path' => $pagePath],
                $this->metricPayload($row) + [
                    'page_url' => $pageUrl !== '' ? $pageUrl : null,
                    'page_type' => $pagePath ? $this->pageTypeDetector->detect($pagePath) : null,
                ],
            );

            $count++;
        }

        return $count;
    }

    private function importCountries(string $path, string $date): int
    {
        return $this->importDimension($path, $date, ['Land', 'Country'], 'country', GscCountrySnapshot::class);
    }

    private function importDevices(string $path, string $date): int
    {
        return $this->importDimension($path, $date, ['Apparaat', 'Device'], 'device', GscDeviceSnapshot::class);
    }

    private function importSearchAppearances(string $path, string $date): int
    {
        return $this->importDimension($path, $date, ['Zoekopmaak', 'Search appearance'], 'appearance', GscSearchAppearanceSnapshot::class);
    }

    private function importDates(string $path, string $date): int
    {
        $count = 0;

        foreach ($this->readRows($path) as $row) {
            $dataDate = $this->value($row, ['Datum', 'Date']);

            if ($dataDate === '') {
                continue;
            }

            GscDateSnapshot::query()->updateOrCreate(
                ['date' => $date, 'data_date' => Carbon::parse($dataDate)->toDateString()],
                $this->metricPayload($row),
            );

            $count++;
        }

        return $count;
    }

    /**
     * @param  class-string  $modelClass
     */
    private function importDimension(string $path, string $date, array $dimensionAliases, string $dimensionColumn, string $modelClass): int
    {
        $count = 0;

        foreach ($this->readRows($path) as $row) {
            $dimension = $this->value($row, $dimensionAliases);

            if ($dimension === '') {
                continue;
            }

            $modelClass::query()->updateOrCreate(
                ['date' => $date, $dimensionColumn => $dimension],
                $this->metricPayload($row),
            );

            $count++;
        }

        return $count;
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    private function readRows(string $path): iterable
    {
        if (! is_file($path)) {
            throw new RuntimeException("CSV-bestand niet gevonden: {$path}");
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($this->detectDelimiter($path));

        $headers = null;

        foreach ($file as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $row = array_map(fn ($value): string => trim((string) $value), $row);

            if ($headers === null) {
                $headers = $row;

                continue;
            }

            if (count(array_filter($row, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            yield array_combine($headers, array_pad($row, count($headers), '')) ?: [];
        }
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return ',';
        }

        $line = (string) fgets($handle);
        fclose($handle);

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $aliases
     */
    private function value(array $row, array $aliases, bool $required = true): string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                return trim($row[$alias]);
            }
        }

        if ($required) {
            throw new RuntimeException('CSV-kolom ontbreekt: '.implode(' / ', $aliases));
        }

        return '';
    }

    /**
     * @param  array<string, string>  $row
     * @return array{clicks:int,impressions:int,ctr:float,position:?float}
     */
    private function metricPayload(array $row): array
    {
        return [
            'clicks' => $this->integer($this->value($row, ['Klikken', 'Clicks'])),
            'impressions' => $this->integer($this->value($row, ['Vertoningen', 'Impressions'])),
            'ctr' => $this->ctr($this->value($row, ['CTR'])),
            'position' => $this->decimal($this->value($row, ['Positie', 'Position'])),
        ];
    }

    private function integer(string $value): int
    {
        return (int) str_replace(['.', ','], '', $value);
    }

    private function decimal(string $value): ?float
    {
        $value = trim(str_replace('%', '', $value));

        if ($value === '') {
            return null;
        }

        return (float) str_replace(',', '.', $value);
    }

    private function ctr(string $value): float
    {
        $hasPercent = str_contains($value, '%');
        $decimal = $this->decimal($value) ?? 0.0;

        if ($hasPercent || $decimal > 1) {
            return round($decimal / 100, 4);
        }

        return round($decimal, 4);
    }

    /**
     * @param  array<int, string|array{path:string,name?:string,delete_after?:bool}>  $files
     * @return list<array{path:string,name:string,delete_after:bool}>
     */
    private function normalizeFiles(array $files): array
    {
        return collect($files)
            ->map(function (string|array $file): array {
                if (is_string($file)) {
                    return [
                        'path' => $file,
                        'name' => basename($file),
                        'delete_after' => false,
                    ];
                }

                return [
                    'path' => $file['path'],
                    'name' => $file['name'] ?? basename($file['path']),
                    'delete_after' => (bool) ($file['delete_after'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private function finishSession(GscImportSession $session, float $startedAt, string $status, array $counts, array $warnings, array $errors): array
    {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $payload = [
            'status' => $status,
            'processed_files' => $counts['processed_files'],
            'skipped_files' => $counts['skipped_files'],
            'pages_imported' => $counts['pages'],
            'queries_imported' => $counts['queries'],
            'countries_imported' => $counts['countries'],
            'devices_imported' => $counts['devices'],
            'search_appearances_imported' => $counts['search_appearances'],
            'date_rows_imported' => $counts['date_rows'],
            'duration_ms' => $durationMs,
            'warnings' => $warnings,
            'errors' => $errors,
        ];

        $session->update($payload);

        if (Schema::hasTable('gsc_import_logs')) {
            $logPayload = [
                'date' => $session->import_date,
                'pages_imported' => $counts['pages'],
                'queries_imported' => $counts['queries'],
                'user_id' => $session->user_id,
                'duration_ms' => $durationMs,
                'status' => $status,
                'warnings' => $warnings,
                'errors' => $errors,
            ];

            if (Schema::hasColumn('gsc_import_logs', 'gsc_import_session_id')) {
                $logPayload['gsc_import_session_id'] = $session->id;
            }

            GscImportLog::query()->create($logPayload);
        }

        return ['session_id' => $session->id, 'date' => $session->import_date?->toDateString()] + $payload;
    }
}
