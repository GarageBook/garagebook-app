<?php

namespace App\Console\Commands;

use App\Contracts\Growth\DiscoveryProvider;
use App\Services\Growth\Community2026DiscoveryService;
use App\Services\Growth\Discovery\CsvDiscoveryProvider;
use App\Services\Growth\Discovery\JsonDiscoveryProvider;
use App\Services\Growth\Discovery\WebsiteDiscoveryProvider;
use Illuminate\Console\Command;

class DiscoverCommunity2026Command extends Command
{
    protected $signature = 'garagebook:discover-community2026
        {--file= : CSV of JSON inputbestand}
        {--url=* : Een of meer URLs om te crawlen}
        {--urls= : Komma-gescheiden lijst, of pad naar een tekstbestand met URLs}
        {--output=storage/app/imports/community2026_discovered.csv : Output CSV pad}
        {--limit=100 : Max aantal URLs om te crawlen}';

    protected $description = 'Genereer Community2026 discovery-output zonder te mailen of te queuen.';

    public function handle(Community2026DiscoveryService $service): int
    {
        $providers = $this->buildProviders();

        if ($providers === []) {
            $this->error('Geef --file, --url of --urls op.');

            return self::FAILURE;
        }

        $results = $service->discover($providers);
        $written = $service->writeCsv($results, (string) $this->option('output'));

        $this->info('Discovery voltooid.');
        $this->line('Gevonden: '.count($results));
        $this->line('Geschreven: '.$written);
        $this->line('Output: '.$this->resolveOutputPath((string) $this->option('output')));

        return self::SUCCESS;
    }

    /**
     * @return array<int, DiscoveryProvider>
     */
    private function buildProviders(): array
    {
        $providers = [];
        $file = trim((string) $this->option('file'));

        if ($file !== '') {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            $providers[] = match ($extension) {
                'csv' => new CsvDiscoveryProvider($this->resolveInputPath($file)),
                'json' => new JsonDiscoveryProvider($this->resolveInputPath($file)),
                default => throw new \InvalidArgumentException('Ondersteunde filetypes zijn csv en json.'),
            };
        }

        $urls = array_merge(
            $this->stringOptionList('url'),
            $this->urlsOptionList(),
        );

        if ($urls !== []) {
            $providers[] = new WebsiteDiscoveryProvider($urls, (int) $this->option('limit'));
        }

        return $providers;
    }

    /**
     * @return array<int, string>
     */
    private function stringOptionList(string $option): array
    {
        $value = $this->option($option);

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn (mixed $item): string => trim((string) $item),
                $value,
            )));
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return [];
        }

        $value = trim((string) $value);

        return $value === '' ? [] : [$value];
    }

    /**
     * @return array<int, string>
     */
    private function urlsOptionList(): array
    {
        $value = trim((string) $this->option('urls'));

        if ($value === '') {
            return [];
        }

        $path = $this->resolveInputPath($value);

        if (is_file($path) && is_readable($path)) {
            return array_values(array_filter(array_map(
                fn (string $item): string => trim($item),
                preg_split('/\R/', (string) file_get_contents($path)) ?: [],
            )));
        }

        return array_values(array_filter(array_map(
            fn (string $item): string => trim($item),
            preg_split('/[\s,]+/', $value) ?: [],
        )));
    }

    private function resolveInputPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function resolveOutputPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
