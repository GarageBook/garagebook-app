<?php

namespace App\Services\Growth\Discovery;

use App\Contracts\Growth\DiscoveryProvider;
use App\Data\Growth\DiscoveryResult;

class CsvDiscoveryProvider implements DiscoveryProvider
{
    public function __construct(private readonly string $path) {}

    public function discover(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header): string => trim((string) $header), $headers);
        $results = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $payload = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $payload[$header] = trim((string) ($row[$index] ?? ''));
            }

            $results[] = DiscoveryResult::fromArray($payload, 'csv');
        }

        fclose($handle);

        return $results;
    }
}
