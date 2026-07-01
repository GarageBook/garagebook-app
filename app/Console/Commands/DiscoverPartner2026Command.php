<?php

namespace App\Console\Commands;

use App\Contracts\Growth\DiscoveryProvider;
use App\Services\Growth\Campaigns\CampaignDiscoveryService;
use App\Services\Growth\Campaigns\Partner2026Definition;
use App\Services\Growth\Discovery\CsvDiscoveryProvider;
use App\Services\Growth\Discovery\JsonDiscoveryProvider;
use App\Services\Growth\Discovery\WebsiteDiscoveryProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiscoverPartner2026Command extends Command
{
    protected $signature = 'garagebook:discover-partner2026
        {--file= : CSV of JSON inputbestand}
        {--url=* : Een of meer URLs om te crawlen}
        {--urls= : Komma-gescheiden lijst, of pad naar een tekstbestand met URLs}
        {--seed-output=storage/app/imports/partner2026_seed_urls.txt : Seed URL output pad}
        {--output=storage/app/imports/partner2026_discovered.csv : Output CSV pad}
        {--rejected=storage/app/imports/partner2026_rejected.csv : Rejected CSV pad}
        {--limit=500 : Max aantal URLs om te crawlen}';

    protected $description = 'Genereer Partner2026 discovery-output zonder te mailen of te queuen.';

    public function __construct(
        private readonly Partner2026Definition $definition,
        private readonly CampaignDiscoveryService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $providers = $this->buildProviders();

        if ($providers === []) {
            $seedUrls = $this->seedUrls();
            $this->writeSeedUrls($seedUrls, (string) $this->option('seed-output'));
            $providers[] = new WebsiteDiscoveryProvider($seedUrls, (int) $this->option('limit'), 75, $this->definition->seedLabel());
        }

        if ($providers === []) {
            $this->error('Geen discovery providers beschikbaar.');

            return self::FAILURE;
        }

        $batch = $this->service->discover($this->definition, $providers);
        $discoverRows = array_merge($batch['accepted'], $batch['manual_review']);
        $written = $this->service->writeCsv($this->definition, $discoverRows, (string) $this->option('output'));
        $rejectedWritten = $this->service->writeCsv($this->definition, $batch['rejected'], (string) $this->option('rejected'));

        $this->info($this->definition->name().' discovery voltooid.');
        $this->line('seed urls: '.$this->countSeedUrls());
        $this->line('discovered total: '.$batch['total']);
        $this->line('accepted: '.count($batch['accepted']));
        $this->line('manual review: '.count($batch['manual_review']));
        $this->line('rejected: '.count($batch['rejected']));
        $this->line('written: '.$written);
        $this->line('rejected written: '.$rejectedWritten);
        $this->line('Output: '.$this->resolveOutputPath((string) $this->option('output')));
        $this->line('Rejected output: '.$this->resolveOutputPath((string) $this->option('rejected')));

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
            $providers[] = new WebsiteDiscoveryProvider($urls, (int) $this->option('limit'), 75, $this->definition->seedLabel());
        }

        return $providers;
    }

    /**
     * @return array<int, string>
     */
    private function seedUrls(): array
    {
        $urls = [];

        foreach ($this->definition->discoveryProviders() as $provider) {
            foreach ($provider->urls() as $url) {
                $url = trim($url);

                if ($url !== '') {
                    $urls[$url] = $url;
                }
            }
        }

        sort($urls);

        return array_values($urls);
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function writeSeedUrls(array $urls, string $path): void
    {
        $path = $this->resolveOutputPath($path);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, $urls).PHP_EOL);
    }

    private function countSeedUrls(): int
    {
        $path = $this->resolveOutputPath((string) $this->option('seed-output'));

        if (! is_file($path)) {
            return 0;
        }

        return count(array_filter(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []));
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

        if (preg_match('/^[A-Za-z]:[\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function resolveOutputPath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
