<?php

namespace App\Services\Growth;

use App\Contracts\Growth\DiscoveryProvider;
use App\Data\Growth\DiscoveryResult;
use Illuminate\Support\Facades\File;

class Community2026DiscoveryService
{
    /**
     * @param  iterable<int, DiscoveryProvider>  $providers
     * @return array<int, DiscoveryResult>
     */
    public function discover(iterable $providers): array
    {
        $results = collect();

        foreach ($providers as $provider) {
            foreach ($provider->discover() as $result) {
                if (! $result instanceof DiscoveryResult) {
                    continue;
                }

                $key = $result->dedupeKey();

                if ($key === '') {
                    continue;
                }

                if ($results->has($key)) {
                    $results[$key] = $results[$key]->mergeWith($result);

                    continue;
                }

                $results[$key] = $result;
            }
        }

        return $results->values()->all();
    }

    /**
     * @param  iterable<int, DiscoveryResult>  $results
     */
    public function writeCsv(iterable $results, string $path): int
    {
        $path = $this->resolvePath($path);
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Kan discovery CSV niet schrijven: '.$path);
        }

        fputcsv($handle, $this->headers());

        $count = 0;

        foreach ($results as $result) {
            if (! $result instanceof DiscoveryResult) {
                continue;
            }

            fputcsv($handle, $result->toCsvRow());
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return ['name', 'website', 'email', 'phone', 'city', 'province', 'source_url', 'source_type', 'prospect_type', 'prospect_subtype', 'notes'];
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return base_path('storage/app/imports/community2026_discovered.csv');
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
