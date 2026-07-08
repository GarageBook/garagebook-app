<?php

namespace App\Services\Gsc;

use App\Models\GscPageSnapshot;
use App\Models\GscQuerySnapshot;
use Illuminate\Support\Carbon;
use RuntimeException;
use SplFileObject;

class GscCsvImportService
{
    public function __construct(
        private readonly GscPageTypeDetector $pageTypeDetector,
    ) {}

    /**
     * @return array{pages:int,queries:int,date:string}
     */
    public function import(?string $pagesPath, ?string $queriesPath, string $date): array
    {
        $snapshotDate = Carbon::parse($date)->toDateString();

        return [
            'pages' => $pagesPath ? $this->importPages($pagesPath, $snapshotDate) : 0,
            'queries' => $queriesPath ? $this->importQueries($queriesPath, $snapshotDate) : 0,
            'date' => $snapshotDate,
        ];
    }

    private function importPages(string $path, string $date): int
    {
        $count = 0;

        foreach ($this->readRows($path) as $row) {
            $pageUrl = $this->value($row, 'Pagina');
            $path = $this->pageTypeDetector->pathFromUrl($pageUrl);

            if ($path === null) {
                continue;
            }

            GscPageSnapshot::query()->updateOrCreate(
                [
                    'date' => $date,
                    'path' => $path,
                ],
                [
                    'page_url' => $pageUrl,
                    'clicks' => $this->integer($this->value($row, 'Klikken')),
                    'impressions' => $this->integer($this->value($row, 'Vertoningen')),
                    'ctr' => $this->ctr($this->value($row, 'CTR')),
                    'position' => $this->decimal($this->value($row, 'Positie')),
                    'page_type' => $this->pageTypeDetector->detect($path),
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
            $query = $this->value($row, 'Zoekopdracht');
            $pageUrl = $this->value($row, 'Pagina', false);
            $path = $this->pageTypeDetector->pathFromUrl($pageUrl);

            if ($query === '') {
                continue;
            }

            GscQuerySnapshot::query()->updateOrCreate(
                [
                    'date' => $date,
                    'query' => $query,
                    'path' => $path,
                ],
                [
                    'page_url' => $pageUrl !== '' ? $pageUrl : null,
                    'clicks' => $this->integer($this->value($row, 'Klikken')),
                    'impressions' => $this->integer($this->value($row, 'Vertoningen')),
                    'ctr' => $this->ctr($this->value($row, 'CTR')),
                    'position' => $this->decimal($this->value($row, 'Positie')),
                    'page_type' => $path ? $this->pageTypeDetector->detect($path) : null,
                ],
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
     */
    private function value(array $row, string $key, bool $required = true): string
    {
        if (! array_key_exists($key, $row)) {
            if ($required) {
                throw new RuntimeException("CSV-kolom ontbreekt: {$key}");
            }

            return '';
        }

        return trim($row[$key]);
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
}
