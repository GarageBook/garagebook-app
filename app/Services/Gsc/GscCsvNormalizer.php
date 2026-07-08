<?php

namespace App\Services\Gsc;

use RuntimeException;

class GscCsvNormalizer
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * @return list<string>
     */
    public function headers(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        try {
            $delimiter = $this->detectDelimiter($path);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($row === [null] || $this->isEmptyRow($row)) {
                    continue;
                }

                return array_map(fn ($header): string => $this->normalizeHeader((string) $header), $row);
            }
        } finally {
            fclose($handle);
        }

        return [];
    }

    /**
     * @return array{headers:list<string>,dimension_candidates:list<string>,metric_candidates:list<string>,missing_required:list<string>,has_compare_columns:bool}
     */
    public function profile(array $headers): array
    {
        $normalized = array_map(fn (string $header): string => $this->normalizeHeader($header), $headers);
        $dimensionCandidates = [];
        $metricCandidates = [];
        $hasCompareColumns = false;

        foreach ($normalized as $header) {
            if ($dimension = $this->canonicalDimension($header)) {
                $dimensionCandidates[] = $dimension;
            }

            if ($this->hasDateRangePrefix($header)) {
                $hasCompareColumns = true;
            }

            if ($metric = $this->canonicalMetric($header)) {
                $metricCandidates[] = $metric;
            }
        }

        $metricCandidates = array_values(array_unique($metricCandidates));
        $missing = array_values(array_diff(['clicks', 'impressions', 'ctr', 'position'], $metricCandidates));

        return [
            'headers' => $normalized,
            'dimension_candidates' => array_values(array_unique($dimensionCandidates)),
            'metric_candidates' => $metricCandidates,
            'missing_required' => $missing,
            'has_compare_columns' => $hasCompareColumns,
        ];
    }

    public function canonicalDimension(string $header): ?string
    {
        return match ($this->normalizeHeader($header)) {
            'pagina', "pagina's", 'paginas', 'page', 'pages', 'toppagina', "toppagina's", 'toppaginas', 'top pages', 'top page' => 'pages',
            'zoekopdracht', 'zoekopdrachten', 'meest uitgevoerde zoekopdracht', 'meest uitgevoerde zoekopdrachten', 'query', 'queries', 'top queries', 'top query' => 'queries',
            'land', 'landen', 'country', 'countries' => 'countries',
            'apparaat', 'apparaten', 'device', 'devices' => 'devices',
            'zoekopmaak', 'zoekweergave', 'search appearance', 'search appearances' => 'search_appearance',
            'datum', 'date', 'day' => 'dates',
            default => null,
        };
    }

    public function canonicalMetric(string $header): ?string
    {
        return match ($this->metricHeader($header)) {
            'klikken', 'aantal klikken', 'clicks' => 'clicks',
            'vertoningen', 'impressions' => 'impressions',
            'ctr' => 'ctr',
            'positie', 'position', 'gemiddelde positie', 'average position' => 'position',
            default => null,
        };
    }

    public function hasCompareColumns(array|string $headers): bool
    {
        foreach ((array) $headers as $header) {
            if ($this->hasDateRangePrefix($this->normalizeHeader((string) $header))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    public function rows(string $path): iterable
    {
        if (! is_file($path)) {
            throw new RuntimeException("CSV-bestand niet gevonden: {$path}");
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException("CSV-bestand niet geopend: {$path}");
        }

        try {
            $delimiter = $this->detectDelimiter($path);
            $headers = null;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($row === [null] || $this->isEmptyRow($row)) {
                    continue;
                }

                $row = array_map(fn ($value): string => $this->normalizeCell((string) $value), $row);

                if ($headers === null) {
                    $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), $row);

                    continue;
                }

                yield $this->combineRow($headers, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    public function detectDelimiter(string $path): string
    {
        if (! is_file($path)) {
            return ',';
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            return ',';
        }

        try {
            $samples = [];

            while (($line = fgets($handle)) !== false) {
                $line = $this->stripBom($line);

                if (trim($line) === '') {
                    continue;
                }

                $samples[] = $line;

                if (count($samples) >= 5) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        $semicolonScore = 0;
        $commaScore = 0;

        foreach ($samples as $sample) {
            $semicolonScore += count(str_getcsv($sample, ';'));
            $commaScore += count(str_getcsv($sample, ','));
        }

        return $semicolonScore > $commaScore ? ';' : ',';
    }

    public function normalizeHeader(string $value): string
    {
        return $this->normalizeText($value, true);
    }

    public function normalizeCell(string $value): string
    {
        return $this->normalizeText($value, false);
    }

    private function normalizeText(string $value, bool $lowercase): string
    {
        $value = $this->stripBom($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);

        if ($lowercase) {
            $value = str_replace(['_', '-'], ' ', $value);
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($lowercase) {
            $value = mb_strtolower($value);
        }

        return $value;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $row
     * @return array<string, string>
     */
    private function combineRow(array $headers, array $row): array
    {
        $combined = [];
        $metricSeen = [];
        $values = array_pad($row, count($headers), '');

        foreach ($headers as $index => $header) {
            $value = $values[$index] ?? '';

            if (! array_key_exists($header, $combined)) {
                $combined[$header] = $value;
            }

            if ($metric = $this->canonicalMetric($header)) {
                if (! array_key_exists($metric, $metricSeen)) {
                    $combined[$metric] = $value;
                    $metricSeen[$metric] = true;
                }
            }
        }

        return $combined;
    }

    private function metricHeader(string $header): string
    {
        return $this->stripDateRangePrefix($this->normalizeHeader($header));
    }

    private function stripDateRangePrefix(string $header): string
    {
        return preg_replace('/^\d{1,2}\s+\d{1,2}\s+\d{4}\s+\d{1,2}\s+\d{1,2}\s+\d{4}\s+/u', '', $header) ?? $header;
    }

    private function hasDateRangePrefix(string $header): bool
    {
        return preg_match('/^\d{1,2}\s+\d{1,2}\s+\d{4}\s+\d{1,2}\s+\d{1,2}\s+\d{4}\s+/u', $header) === 1;
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, self::BOM) ? substr($value, strlen(self::BOM)) : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
