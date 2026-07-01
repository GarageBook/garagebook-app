<?php

namespace App\Services\Growth;

use App\Contracts\Growth\DiscoveryProvider;
use App\Data\Growth\DiscoveryResult;
use App\Services\Growth\Campaigns\CampaignDiscoveryService as GenericDiscoveryService;
use App\Services\Growth\Campaigns\Community2026Definition;

class Community2026DiscoveryService
{
    public function __construct(
        private readonly GenericDiscoveryService $service,
        private readonly Community2026DiscoveryQualityService $quality,
        private readonly Community2026Definition $definition,
    ) {}

    /**
     * @param  iterable<int, DiscoveryProvider>  $providers
     * @return array{accepted: array<int, DiscoveryResult>, manual_review: array<int, DiscoveryResult>, rejected: array<int, DiscoveryResult>, total: int}
     */
    public function discover(iterable $providers): array
    {
        return $this->service->discover($this->definition, $providers);
    }

    /**
     * @param  iterable<int, DiscoveryResult>  $results
     */
    public function writeCsv(iterable $results, string $path): int
    {
        return $this->service->writeCsv($this->definition, $results, $path);
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return $this->service->headers();
    }
}
