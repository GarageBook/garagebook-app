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
use Throwable;

class GscCsvImportService
{
    public function __construct(
        private readonly GscPageTypeDetector $pageTypeDetector,
        private readonly GscCsvTypeDetector $typeDetector,
        private readonly GscCsvNormalizer $normalizer,
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

        $sessionPayload = [
            'import_date' => $snapshotDate,
            'user_id' => $userId,
            'status' => 'pending',
            'total_files' => count($normalizedFiles),
            'warnings' => [],
            'errors' => [],
        ];

        if (Schema::hasColumn('gsc_import_sessions', 'notices')) {
            $sessionPayload['notices'] = [];
        }

        $session = GscImportSession::query()->create($sessionPayload);

        $warnings = [];
        $notices = [];
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
            'intentionally_skipped_files' => 0,
        ];

        try {
            if (! $replace && $this->hasSnapshotsForDate($snapshotDate)) {
                $message = 'Er bestaat al GSC-data voor deze datum. Kies bestaande data vervangen om opnieuw te importeren.';
                $counts['skipped_files'] = count($normalizedFiles);

                return $this->finishSession($session, $startedAt, 'failed', $counts, [], [], [$message]);
            }

            if ($replace) {
                $this->deleteSnapshotsForDate($snapshotDate);
                $notices[] = 'Bestaande GSC-data voor '.$snapshotDate.' is vervangen.';
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
                    if ($type === GscCsvTypeDetector::FILTERS) {
                        $notices[] = $file['name'].': bewust overgeslagen (filters).';
                        $counts['intentionally_skipped_files']++;
                    } else {
                        $warnings[] = $this->skippedFileWarning($file['name'], $file['path'], $type);
                    }

                    $counts['skipped_files']++;

                    continue;
                }

                if ($this->normalizer->hasCompareColumns($this->typeDetector->headers($file['path']))) {
                    $notices[] = $file['name'].': Vergelijkingskolommen gedetecteerd; alleen nieuwste periode geïmporteerd.';
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
                ? 'failed'
                : ($warnings !== [] ? 'completed_with_warnings' : 'completed');

            return $this->finishSession($session, $startedAt, $status, $counts, $warnings, $notices, $errors);
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
            $pageUrl = $this->value($row, ['pagina', "pagina's", 'paginas', 'page', 'pages', 'toppagina', "toppagina's", 'toppaginas', 'top pages', 'top page']);
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
            $query = $this->value($row, ['zoekopdracht', 'zoekopdrachten', 'meest uitgevoerde zoekopdracht', 'meest uitgevoerde zoekopdrachten', 'query', 'queries', 'top queries', 'top query']);
            $pageUrl = $this->value($row, ['pagina', "pagina's", 'paginas', 'page', 'pages', 'toppagina', "toppagina's", 'toppaginas', 'top pages', 'top page'], false);
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
        return $this->importDimension($path, $date, ['land', 'landen', 'country', 'countries'], 'country', GscCountrySnapshot::class);
    }

    private function importDevices(string $path, string $date): int
    {
        return $this->importDimension($path, $date, ['apparaat', 'apparaten', 'device', 'devices'], 'device', GscDeviceSnapshot::class);
    }

    private function importSearchAppearances(string $path, string $date): int
    {
        return $this->importDimension($path, $date, ['zoekopmaak', 'zoekweergave', 'search appearance', 'search appearances'], 'appearance', GscSearchAppearanceSnapshot::class);
    }

    private function importDates(string $path, string $date): int
    {
        $count = 0;

        foreach ($this->readRows($path) as $row) {
            $dataDate = $this->value($row, ['datum', 'date', 'day']);

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
        return $this->normalizer->rows($path);
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
            'clicks' => $this->integer($this->value($row, ['clicks', 'klikken', 'aantal klikken'])),
            'impressions' => $this->integer($this->value($row, ['impressions', 'vertoningen'])),
            'ctr' => $this->ctr($this->value($row, ['ctr'])),
            'position' => $this->decimal($this->value($row, ['position', 'positie', 'gemiddelde positie', 'average position'])),
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

    private function finishSession(GscImportSession $session, float $startedAt, string $status, array $counts, array $warnings, array $notices, array $errors): array
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
            'notices' => $notices,
            'errors' => $errors,
        ];

        $sessionPayload = $payload;
        if (! Schema::hasColumn('gsc_import_sessions', 'notices')) {
            unset($sessionPayload['notices']);
        }

        $session->update($sessionPayload);

        if (Schema::hasTable('gsc_import_logs')) {
            $logPayload = [
                'date' => $session->import_date,
                'pages_imported' => $counts['pages'],
                'queries_imported' => $counts['queries'],
                'user_id' => $session->user_id,
                'duration_ms' => $durationMs,
                'status' => $status,
                'warnings' => $warnings,
                'notices' => $notices,
                'errors' => $errors,
            ];

            if (! Schema::hasColumn('gsc_import_logs', 'notices')) {
                unset($logPayload['notices']);
            }

            if (Schema::hasColumn('gsc_import_logs', 'gsc_import_session_id')) {
                $logPayload['gsc_import_session_id'] = $session->id;
            }

            GscImportLog::query()->create($logPayload);
        }

        return ['session_id' => $session->id, 'date' => $session->import_date?->toDateString(), 'intentionally_skipped_files' => $counts['intentionally_skipped_files'] ?? 0] + $payload;
    }

    private function skippedFileWarning(string $name, string $path, string $type): string
    {
        if ($type === GscCsvTypeDetector::FILTERS) {
            return $name.': bewust overgeslagen (filters).';
        }

        $headers = $this->typeDetector->headers($path);
        $profile = $this->normalizer->profile($headers);
        $headerList = $headers === [] ? '-' : implode(', ', $headers);
        $dimensions = $profile['dimension_candidates'] === [] ? '-' : implode(', ', $profile['dimension_candidates']);
        $metrics = $profile['metric_candidates'] === [] ? '-' : implode(', ', $profile['metric_candidates']);
        $missing = $profile['missing_required'] === [] ? '-' : implode(', ', $profile['missing_required']);

        return "Bestand: {$name}
Headers: {$headerList}
Herkende dimensiekandidaten: {$dimensions}
Herkende metriekandidaten: {$metrics}
Ontbrekende vereiste velden: {$missing}
Waarom onbekend: niet herkend als ondersteunde GSC-export.";
    }
}
