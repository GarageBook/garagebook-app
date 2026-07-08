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

                $values = array_pad($row, count($headers), '');

                yield array_combine($headers, $values) ?: [];
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
